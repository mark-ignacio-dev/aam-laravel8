<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\API\CreateAccountAvatarAPIRequest;
use App\Http\Requests\API\UpdateAccountAvatarAPIRequest;
use App\Models\AccountAvatar;
use App\Repositories\AccountRepository;
use App\Repositories\AccountAvatarRepository;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use App\Http\Controllers\AppBaseController;
use Response;

use App\Transformers\AccountAvatarTransformer;
use League\Fractal\Manager;
use League\Fractal\Resource\Collection;
use League\Fractal\Resource\Item;
use League\Fractal\Serializer\JsonApiSerializer;


/**
 * Class AccountAvatarController
 * @package App\Http\Controllers\API
 *
 * @OA\Response(
 *   response="AccountAvatar",
 *   description="successful operation",
 *   @OA\MediaType(
 *     mediaType="application/json",
 *     @OA\Schema(
 *        allOf={@OA\Schema(ref="/jsonapi.org-schema.json#/components/schemas/success")},
 *        @OA\Property(
 *         property="data",
 *         type="object",
 *         ref="#/components/schemas/account_avatar"
 *       )
 *     )
 *   )
 * ),
 * @OA\Response(
 *   response="AccountAvatars",
 *   description="successful operation",
 *   @OA\MediaType(
 *     mediaType="application/json",
 *     @OA\Schema(
 *        allOf={@OA\Schema(ref="/jsonapi.org-schema.json#/components/schemas/success")},
 *        @OA\Property(
 *         property="data",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/AccountAvatar")
 *       )
 *     )
 *   )
 * )
 */
class AccountAvatarAPIController extends AppBaseController
{
    /** @var  AccountAvatarRepository */
    private $accountAvatarRepository;

    public function __construct(AccountAvatarRepository $accountAvatarRepo, AccountRepository $accountRepo)
    {
        $this->accountAvatarRepository = $accountAvatarRepo;
        $this->accountRepository = $accountRepo;
    }


    /**
     * @param CreateAccountAvatarAPIRequest $request
     * @return Response
     *
     * @OA\Post(
     *   path="/avatar/{id}",
     *   operationId="uploadAvatar",
     *   summary="Upload an avatar image",
     *   tags={"Avatar"},
     *   @OA\RequestBody(
     *     description="Upload images request body",
     *     @OA\MediaType(
     *       mediaType="multipart/form-data",
     *       @OA\Schema(
     *         type="object",
     *           @OA\Property(
     *            property="avatar",
     *            type="string",
     *            format="binary",
     *          )
     *       )
     *     )
     *   ),
     *   @OA\Parameter(
     *     name="id",
     *     description="ID of Account",
     *     @OA\Schema(ref="#/components/schemas/Account/properties/AccountID"),
     *     required=true,
     *     in="path"
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="successful operation",
     *     ref="#/components/responses/AccountAvatar"
     *   )
     * )
     */
    public function store($id, CreateAccountAvatarAPIRequest $request)
    {
        $file = $request->file('avatar');
        $account = $this->accountRepository->find($id);
        if (!$account) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException();
        }

        $hash = sha1($account->Email). '-'.$id;
        $filepath = 'profile/'.substr($hash, 0, 2);
        if ($extension = $file->guessExtension()) {
            $hash .= '.'.$extension;
        }

        $url = $file->storeAs($filepath, $hash, 'do-vos-media');
        $prefix = config('filesystems.disks.do-vos-media.root');

        try {
            $accountAvatar = $this->accountAvatarRepository->create([
              'AccountID'=>(int)$id,
              'AvatarURL'=>'https://vos-media.nyc3.digitaloceanspaces.com/'.$prefix.$url,
            ]);
        } catch (\Exception $e) {
            return response()->json(['errors'=>[['title'=>'Internal server error', 'status'=>500]]], 500);
        }

        $manager = new Manager();
        $manager->setSerializer(new JsonApiSerializer());
        $resource = new Item($accountAvatar, new AccountAvatarTransformer);
        return response()->json((new Manager)->createData($resource)->toArray());
    }

    /**
     * @param int $id
     * @return Response
     *
     * @OA\Get(
     *   path="/avatar/{account_id}",
     *   summary="Fetch an avatar by AccountID",
     *   tags={"Avatar"},
     *   description="Get Account Avatar",
     *   @OA\MediaType(
     *     mediaType="application/json",
     *   ),
     *   @OA\Parameter(
     *     name="id",
     *     description="ID of Account",
     *     @OA\Schema(ref="#/components/schemas/Account/properties/AccountID"),
     *     required=true,
     *     in="path"
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="successful operation",
     *     ref="#/components/responses/AccountAvatar"
     *   )
     * )
     */
    public function show($id)
    {
        /** @var AccountAvatar $accountAvatar */
        $accountAvatar = $this->accountAvatarRepository->findByAccountID($id);

        if (empty($accountAvatar)) {
            return $this->sendError('Account Avatar not found');
        }

        return $this->sendResponse($accountAvatar->toArray(), 'Account Avatar retrieved successfully');
    }

    /**
     * @param int $id
     * @param UpdateAccountAvatarAPIRequest $request
     * @return Response
     *
     * @OA\Patch(
     *   path="/avatar/{account_id}",
     *   summary="Update the avatar specified by the AccountID",
     *   tags={"Avatar"},
     *   description="Update Avatar",
     *   @OA\MediaType(
     *     mediaType="application/json",
     *   ),
     *   @OA\Parameter(
     *     name="id",
     *     in="path",
     *     description="ID of Account",
     *     required=true,
     *     @OA\Schema(ref="#/components/schemas/Account/properties/AccountID")
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="successful operation",
     *     ref="#/components/responses/AccountAvatar"
     *   )
     * )
     */
    public function update($id, UpdateAccountAvatarAPIRequest $request)
    {
        $input = $request->all();

        /** @var AccountAvatar $accountAvatar */
        $accountAvatar = $this->accountAvatarRepository->findByAccountID($id);

        if (empty($accountAvatar)) {
            return $this->sendError('Account Avatar not found');
        }

        $accountAvatar = $this->accountAvatarRepository->update($input, $id);

        return $this->sendResponse($accountAvatar->toArray(), 'AccountAvatar updated successfully');
    }

    /**
     * @param int $id
     * @return Response
     *
     * @OA\Delete(
     *   path="/avatar/{account_id}",
     *   summary="Remove the avatar specified by the AccountID",
     *   tags={"Avatar"},
     *   description="Delete AccountAvatar",
     *   @OA\MediaType(
     *     mediaType="application/json",
     *   ),
     *   @OA\Parameter(
     *     name="id",
     *     description="ID of Account",
     *     @OA\Schema(ref="#/components/schemas/Account/properties/AccountID"),
     *     required=true,
     *     in="path"
     *   ),
     *   @OA\Response(
     *     response="200",
     *     description="success",
     *     @OA\Schema(ref="/jsonapi.org-schema.json#/components/schemas/success"),
     *   )
     * )
     */
    public function destroy($id)
    {
        /** @var AccountAvatar $accountAvatar */
        $accountAvatar = $this->accountAvatarRepository->find($id);

        if (empty($accountAvatar)) {
            return $this->sendError('Account Avatar not found');
        }

        $accountAvatar->delete();

        return $this->sendResponse($id, 'Account Avatar deleted successfully');
    }
}
