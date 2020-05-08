<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\AppBaseController;
use App\Jobs\ThumbnailVideo;
use App\Models\Swing;
use App\Models\Instructor;
use App\Repositories\SwingRepository;
use App\Repositories\InstructorRepository;
use App\Transformers\SwingAnalysisTransformer;
use Carbon\Carbon;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

use League\Fractal\Manager;
use League\Fractal\Resource\Collection;
use League\Fractal\Resource\Item;
use League\Fractal\Serializer\JsonApiSerializer;
use Response;


/**
 * Class LockerController
 * @package App\Http\Controllers\API
 *
 * @OA\Response(
 *   response="Locker",
 *   description="successful operation",
 *   @OA\MediaType(
 *     mediaType="application/json",
 *     @OA\Schema(
 *        allOf={@OA\Schema(ref="./jsonapi-schema.json#/definitions/success")},
 *
 *        @OA\Property(
 *         property="data",
 *         type="object",
 *         ref="#/components/schemas/LegacySwing"
 *       )
 *     )
 *   )
 * ),
 * @OA\Response(
 *   response="Swings",
 *   description="successful operation",
 *   @OA\MediaType(
 *     mediaType="application/json",
 *     @OA\Schema(
 *        allOf={@OA\Schema(ref="./jsonapi-schema.json#/definitions/success")},
 *        @OA\Property(
 *         property="data",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/LegacySwing")
 *       )
 *     )
 *   )
 * )
 */

class LockerAPIController extends AppBaseController
{
    /** @var  SwingRepository */
    private $swingRepository;

    public function __construct(SwingRepository $swingRepo)
    {
        $this->swingRepository = $swingRepo;
    }

    /**
     * @param Request $request
     * @return Response
     *
     * @OA\Get(
     *   path="/locker/{accountId}",
     *   tags={"Locker"},
     *   summary="Get a listing of your Videos.",
     *   description="Get all your videos",
     *   @OA\MediaType(
     *     mediaType="application/json"
     *   ),
     *   @OA\Parameter(
     *     name="accountId",
     *     description="id of Account",
     *     @OA\Schema(ref="#/components/schemas/account/properties/id"),
     *     required=false,
     *     in="path"
     *   ),
     *   @OA\Parameter(
     *     name="ids",
     *     description="Comma separated list of specific video ids",
     *     required=false,
     *     @OA\Schema(
     *       type="string",
     *     ),
     *     in="query"
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="List of all of videos connected to the logged in account which are not deleted",
     *     @OA\MediaType(
     *     mediaType="application/json",
     *
     *       @OA\Schema(
     *        allOf={@OA\Schema(ref="./jsonapi-schema.json#/definitions/success")},
     *        @OA\Property(
     *           property="data",
     *           type="array",
     *           @OA\Items(ref="#/components/schemas/swing")
     *         )
     *       )
     *     )
     *   )
     * )
     */
    public function index(Request $request, $accountId=false)
    {
        $user = $request->user();
        $limit = $request->get('limit') ? intval($request->get('limit')) : 100;

        //only read the accountId if the user is an instructor
        if ($user->IsInstructor) {
            $instructorRepositry = new InstructorRepository(app());
            $list = $instructorRepositry->students($user->AccountID, false, [$accountId]);
            if (! count($list)) {
                throw new \Illuminate\Database\Eloquent\ModelNotFoundException();
            }
        } else {
            $accountId = $user->AccountID;
        }

        //new or rejected statuses
        $searchParams = ['AccountID'=>$accountId, 'Deleted'=>false];

        //are you requesting specific access for attachment viewing?
        if ($ids = $request->input('ids')) {
            $searchParams['SwingID'] = explode(',', $ids);
        } else {
            //or are you just browsing your locker?
            $searchParams['SwingStatusID'] = [0,4];
        }
        $swings = $this->swingRepository->all(
                $searchParams,
                $request->get('skip'),
                $limit
        );

        $resource = new Collection($swings->toArray(), [$this, 'swingRecordTranslate']);
        return response()->json((new Manager)->createData($resource)->toArray());
    }

    /**
     * @param Request $request
     * @return Response
     *
     * @OA\Get(
     *   path="/locker/{swingId}/analysis",
     *   tags={"Locker"},
     *   summary="Get an analysis for one video",
     *   @OA\MediaType(
     *     mediaType="application/json"
     *   ),
     *   @OA\Parameter(
     *     name="swingId",
     *     description="ID of Swing",
     *     @OA\Schema(ref="#/components/schemas/swing/properties/id"),
     *     required=true,
     *     in="path"
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="List of all of swing analysis videos for one swing",
     *     @OA\MediaType(
     *     mediaType="application/json",
     *
     *       @OA\Schema(
     *        allOf={@OA\Schema(ref="./jsonapi-schema.json#/definitions/success")},
     *        @OA\Property(
     *           property="data",
     *           type="array",
     *           @OA\Items(ref="#/components/schemas/lessonvideo")
     *         )
     *       )
     *     )
     *   )
     * )
     */
    public function swingAnalysis(Request $request, $swingId)
    {
        $user = $request->user();
        $limit = $request->get('limit') ? intval($request->get('limit')) : 20;

        $searchParams = ['Deleted'=>false, 'SwingStatusID'=>3, 'SwingID' => (int)$swingId];
        $searchParams['SwingID'] = (int)$swingId;
        if ($user->IsInstructor) {
            $searchParams['InstructorID'] = (int)$user->AccountID;
        } else {
            $accountId = $user->AccountID;
            $searchParams['AccountID'] = (int)$user->AccountID;
        }

        $swings = $this->swingRepository->all(
                $searchParams,
                $request->get('skip'),
                $limit
        );

        $manager = new Manager();
        $manager->setSerializer(new JsonApiSerializer());
        $resource = (new Collection($swings, new SwingAnalysisTransformer));
        return response()->json((new Manager)->createData($resource)->toArray());
    }

    /**
     * @param Request $request
     * @return Response
     *
     * @OA\Get(
     *   path="/videolessons",
     *   tags={"Locker"},
     *   summary="Get all analysis videos for instructor from past year",
     *   @OA\MediaType(
     *     mediaType="application/json"
     *   ),
     *   @OA\Parameter(
     *     name="daysAgo",
     *     description="Number of days to search for analysis video uploads",
     *     @OA\Schema(
     *       type="string",
     *       format="datetime",
     *     ),
     *     required=false,
     *     example=30,
     *     in="query"
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="List of all swings for one instructor for the past year",
     *     @OA\MediaType(
     *     mediaType="application/json",
     *       @OA\Schema(
     *        allOf={@OA\Schema(ref="./jsonapi-schema.json#/definitions/success")},
     *        @OA\Property(
     *           property="data",
     *           type="array",
     *           @OA\Items(ref="#/components/schemas/lessonvideo")
     *         )
     *       )
     *     )
     *   )
     * )
     */
    public function videoLessonIndex(Request $request)
    {
        $user = $request->user();
        $limit = $request->get('limit') ? intval($request->get('limit')) : 20;
        $daysAgo = (int)$request->query('daysAgo');
        if ($daysAgo == 0) {
            $daysAgo = 365;
        }
        $date  = \Carbon\Carbon::now()->subDays($daysAgo);

        if (!$user || (!$user->IsInstructor && !$user->isApiAgent())) {
            return response()->json('Unauthorized', 403);
        }
        $instructorId = (int)$user->AccountID;
        if ($user && $user->isApiAgent()) {
            if ($request->has('instructor_id')) {
                $instructorId = (int)$request->query('instructor_id');
            }
        }

        $searchParams = [
            'InstructorID'  => $instructorId,
            'Deleted'       => false,
            'SwingStatusID' => [3,2],
        ];

        $swings = $this->swingRepository->searchBrokenVideos(
                $searchParams,
                $date,
                $request->get('skip'),
                $limit
        );

        $manager = new Manager();
        $manager->setSerializer(new JsonApiSerializer());
        $resource = (new Collection($swings, new SwingAnalysisTransformer));
        return response()->json((new Manager)->createData($resource)->toArray());
    }

    /**
     * @param Request $request
     * @return Response
     *
     * @OA\Post(
     *   path="/locker/assignSwings",
     *   summary="Assign a swing to yourself as instructor",
     *   tags={"Locker"},
     *   @OA\RequestBody(
     *     description="list of swing IDs",
     *     required=false,
     *     @OA\MediaType(
     *       mediaType="application/json",
     *       @OA\Schema(
     *         @OA\Property(
     *           property="instructor_id",
     *           type="integer",
     *           format="integer"
     *         ),
     *         @OA\Property(
     *           property="swing_ids",
     *           description="Swing ID",
     *           type="array",
     *           @OA\Items(type="integer")
     *         ),
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="List of all of videos connected to the logged in account which are not deleted",
     *     @OA\MediaType(
     *     mediaType="application/json",
     *
     *     @OA\Schema(
     *      allOf={@OA\Schema(ref="./jsonapi-schema.json#/definitions/success")},
     *      @OA\Property(
     *         property="data",
     *         type="array",
     *         @OA\Items(ref="#/components/schemas/swing")
     *       )
     *      )
     *      )
     *   )
     * )
     */
    public function assignSwings(Request $request) {
        $instructor = Instructor::find(
            $request->input('instructor_id')
        );
        if (!$instructor) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException();
        }
        //allow instructor assign to self,
        //allow user assign to instructor if connection
        //is already made between the two accounts.

        $user = \Auth::user();
        if ($user->isApiAgent()
            ||
            $user->hasInstructorConnection($instructor->InstructorID)
            ||
            $user->AccountID == $instructor->InstructorID
        ) {
            //authorized
        } else {
            return response()->json('Unauthorized', 403);
        }

        $swingIdList = $request->input('swing_ids');

        //TODO: ensure the AccountIDs are related to the instructor or academy
        //X-AUTHORIZE
        //['SwingID' => $swingid, 'AccountID' => $request->input('AccountID')];

        $swings = $this->swingRepository->all([
            'SwingID'=>$swingIdList,
            'Deleted'=>0,
        ]);

        $swings->each(function($item) use ($instructor) {
            $item->SwingStatusID = 1;
            $item->InstructorID = $instructor->InstructorID;
            $item->DateAccepted = \Carbon\Carbon::now();
            //TODO: should we set this?  the old API did
            //$item->DateUploaded = $today->format('Y-m-d H:i:s');
            //$item->Charge = $instructor->Fee;
            //$item->ProCharge = -(1.0 - $instructor->DiscountRate) * $instructor->Fee;
            $item->save();
        });

        $resource = new Collection($swings->toArray(), [$this, 'swingRecordTranslate']);
        return response()->json((new Manager)->createData($resource)->toArray());
    }


    /**
     * @OA\Schema(
     *   schema="swing",
     *   required={""},
     *   @OA\Property(
     *     property="id",
     *     description="Swing ID",
     *     type="integer",
     *          format="int32"
     *   ),
     *   @OA\Property(
     *     property="account_id",
     *     description="Account ID",
     *     type="integer",
     *          format="int32"
     *   ),
     *   @OA\Property(
     *     property="title",
     *     description="title or description",
     *     type="string"
     *   ),
     *   @OA\Property(
     *     property="date_uploaded",
     *     description="Date video was uploaded",
     *     type="string"
     *   ),
     *   @OA\Property(
     *     property="thumb_url",
     *     description="URL of thumbnail picture",
     *     type="string"
     *   ),
     *   @OA\Property(
     *     property="video_url",
     *     description="URL of video file",
     *     type="string"
     *   ),
     *   @OA\Property(
     *     property="vimeo_id",
     *     description="ID of video on Vimeo",
     *     type="string"
     *   ),
     *   @OA\Property(
     *     property="status_id",
     *     description="SwingStatusID",
     *     type="integer",
     *          format="int32"
     *   ),
     * )
     */
    public function swingRecordTranslate(array $swing) {
        $videoUrl = $swing['VideoPath'];
        if (substr($videoUrl, 0, 4) !== 'http' && $videoUrl != '') {
            $videoUrl = 'https://v1sports.com/SwingStore/'.$swing['VideoPath'];
        }
        $thumbUrl = str_replace( ['.mp4', '.webm', '.bin', '.mov'], '.jpg', $swing['VideoPath']);
        if (substr($thumbUrl, 0, 4) !== 'http' && $thumbUrl != '') {
            $thumbUrl = 'https://v1sports.com/SwingStore/'.$thumbUrl;
        }
        $dateUploaded = $swing['DateUploaded'];
        if (!$swing['DateUploaded'] instanceof \DateTime) {
            $dateUploaded = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s.v', $swing['DateUploaded'], 'America/New_York');
        }
        //TZ: Dates are stored in OS local timezones in MSSQL (probably Amercia/New_York)
        return [
            'id'            => (int) $swing['SwingID'],
            'type'          => 'video',
            'attributes'    => [
            'account_id'    => (int) $swing['AccountID'],
            'title'         => $swing['Description'],
            'video_url'     => $videoUrl,
            'thumb_url'     => $thumbUrl,
            'vimeo_id'      => $swing['VimeoID'],
            'status_id'     => $swing['SwingStatusID'],
            //'thumb_url'    => 'https://v1sports.com/SwingStore/190424231844IP9M2449503.jpg',
            'date_uploaded' => $dateUploaded,
            ],
        ];
    }

    /**
     * @OA\Post(
     *   path="/locker/",
     *   summary="Create a new locker item",
     *   tags={"Locker"},
     *   @OA\RequestBody(
     *     description="",
     *     required=false,
     *   ),
     *   @OA\Response(
     *     response=201,
     *     description="Create a locker item, send URL to PUT a new file",
     *     @OA\MediaType(
     *       mediaType="application/json",
     *
     *       @OA\Schema(
     *         allOf={@OA\Schema(ref="./jsonapi-schema.json#/definitions/success")},
     *         @OA\Property(
     *           property="data",
     *           type="array",
     *           @OA\Items(ref="#/components/schemas/swing")
     *         )
     *       )
     *     )
     *   )
     * )
     */
    public function store(Request $request)
    {
        $user = $request->user();
        $limit = $request->get('limit') ? intval($request->get('limit')) : 100;

        $nowInNyc =  Carbon::now(new DateTimeZone('America/New_York'));
        $lockerItem = $this->swingRepository->create([
            'Description'   => $request->input('description'),
            'SwingStatusID' => 0,
            'DateUploaded'  => $nowInNyc,
            'AccountID'     => $user->AccountID,
            'VideoPath'     => '',
            'VimeoID'       => '',
        ]);
        $resource = new Item($lockerItem->toArray(), [$this, 'swingRecordTranslate']);
        return response()
            ->json((new Manager)->createData($resource)->toArray(), 201)
            ->header('Location', route('api.locker-upload-put').'?id='.$lockerItem->SwingID);
    }

    /**
     * @OA\Put(
     *   path="/locker/upload",
     *   summary="Uplaod video resource for locker item created previously",
     *   tags={"Locker"},
     *   @OA\RequestBody(
     *     description="",
     *     required=false,
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Create a locker item, send URL to PUT a new file",
     *     @OA\MediaType(
     *       mediaType="application/json",
     *
     *       @OA\Schema(
     *         allOf={@OA\Schema(ref="./jsonapi-schema.json#/definitions/success")},
     *         @OA\Property(
     *           property="data",
     *           type="array",
     *           @OA\Items(ref="#/components/schemas/swing")
     *         )
     *       )
     *     )
     *   )
     * )
     */
    public function upload(Request $request)
    {
        $user = $request->user();
        $lockerItemId = $request->query('id') ? intval($request->query('id')) : null;
        if ($lockerItemId == null) {
            throw new InvalidArgumentException('id query parameter missing or invalid');
        }
        //find Content-Length header
        $byteLimit   = (int)$request->header('content-length') ?? 0;
        if ($byteLimit == 0) {
            throw new InvalidArgumentException('Content-Length header must be specified');
        }
        $contentType   = $request->header('content-type') ?? '';
        if (!in_array($contentType, ['video/mp4', 'video/webm', 'video/quicktime', 'application/octet-stream'])) {
            throw new InvalidArgumentException('Content-Type header must be specified.  Acceptable values: video/mp4, video/webm, video/quicktime');
        }

        $lockerItem = $this->swingRepository->find($lockerItemId);
        if ($lockerItem->AccountID != $user->AccountID) {
            throw new AuthorizationException();
        }
        /*
        if ($lockerItem->VideoPath != '') {
            throw new AuthorizationException('Video already uploaded');
        }
         */
        $destinationFilename = 'swings/'.date('Y').'/'.date('Ymdhis');
        if (in_array(config('app.env'), ['testing', 'local'])) {
            $destinationFilename = 'test/'.$destinationFilename;
        }
        switch ($contentType) {
            case 'video/mp4':
                $destinationFilename .= '.mp4';
                break;
            case 'video/webm':
                $destinationFilename .= '.webm';
                break;
            case 'video/quicktime':
                $destinationFilename .= '.mov';
                break;
            default:
                $destinationFilename .= '.bin';
                break;
        }

        /*
        $bytesCopied = $this->copyInputStreamToS3($destinationFilename, $byteLimit);

        //echo "Byte Limit is ".$byteLimit . " \n";
        //echo "Copied byes: ".$bytesCopied . " \n";

        $lockerItem->VideoPath = 'https://vos-media.nyc3.digitaloceanspaces.com/'.$destinationFilename;
        $lockerItem->save();
         */
        ThumbnailVideo::dispatch($lockerItem->SwingID);

        $resource = new Item($lockerItem->toArray(), [$this, 'swingRecordTranslate']);
        return response()
            ->json((new Manager)->createData($resource)->toArray(), 200);
    }

    public function copyInputStreamToS3($dest, $byteLimit=0) {
        $bucket = 'vos-media';
        //making the object from the Provider calls "registerStreamWrapper"
        //so even if $s3Client is not used here, it is required to be make()'d
        $s3Client = \app()->make('s3-client');

        //64-bit arch has hardcoded internal buffer of 8k
        //stream_copy_to_stream has flexible buffer limit
        $chunksize =  8192 * 24;

        $input  = @fopen('php://input', 'rb');
        //TODO support mode 'ab' with resumable uploads
        $output = @fopen('s3://'.$bucket.'/'.$dest, 'wb', false, stream_context_create([
            's3' => [
                'ACL' => 'public-read',
            ]])
        );

        $bytes = 0;
        while (!feof($input)) {
            $bytes += stream_copy_to_stream($input, $output, $chunksize);
            if ($byteLimit && $bytes > $byteLimit) {
                //do nothing
            }
        }
        @fclose($oputput);
        @fclose($input);
        return $bytes;
    }
}
