<?php

use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

class TestUser extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::statement('DELETE FROM [Accounts]');
        DB::statement('DBCC CHECKIDENT ([Accounts], RESEED, 0)');
        DB::statement('DELETE FROM [AcademyInstructors]');
        DB::statement('DELETE FROM [AcademyStudents]');
        DB::statement('DELETE FROM [Instructors]');
        DB::statement('DELETE FROM [InstructorStudentsMulti]');
        $faker = Faker::create();
        $faker->seed(4321);
        $paulineId = DB::table('Accounts')->insertGetId([
//            'AccountID'          => 1,
            'FirstName'          => 'Pauline',
            'LastName'           => 'Pro',
            'Email'              => 'pauline@example.com',
            'PasswordHash'       => \Hash::make('password'),
        ]);
        $carlId = DB::table('Accounts')->insertGetId([
//            'AccountID'          => 2,
            'FirstName'          => 'Carl',
            'LastName'           => 'Customer',
            'Email'              => 'carl@customer.test',
            'PasswordHash'       => \Hash::make('password'),
        ]);
        $shyId = DB::table('Accounts')->insertGetId([
//            'AccountID'          => 3,
            'FirstName'          => 'Shy',
            'LastName'           => 'Guy',
            'Email'              => 'shy@example.com',
            'PasswordHash'       => \Hash::make('password'),
        ]);

        $nancyId = DB::table('Accounts')->insertGetId([
            'FirstName'          => 'Nancy',
            'LastName'           => 'NewCustomer',
            'Email'              => 'nancy@customer.test',
            'PasswordHash'       => \Hash::make('password'),
        ]);

        DB::table('Instructors')->insert([
            'AcademyID'    => '',
            'InstructorID' => $paulineId,
            'Title'        => 'Sr. Pro Teacher',
            'HeadShot'     => 'https://vos-media.nyc3.digitaloceanspaces.com/profile/Screenshot%20from%202019-09-27%2017-22-38.png',
        ]);

        DB::table('Instructors')->insert([
            'AcademyID'    => '',
            'InstructorID' => $shyId,
            'Title'        => 'common enemy in the Mario series',
            'HeadShot'     => 'https://cdn.fstoppers.com/styles/large-16-9/s3/lead/2014/12/fstoppers-dylan-patrick-setting-up-a-successful-headshot-session-8.jpg',
        ]);


        DB::table('AcademyInstructors')->insert([
            'AcademyID'    => 'V1AC',
            'InstructorID' => $paulineId,
            'IsMaster'     => 1,
            'IsEnabled'    => 1,
            'IsHidden'     => 0
        ]);

        DB::table('AcademyInstructors')->insert([
            'AcademyID'    => 'SHYG',
            'InstructorID' => $shyId,
            'IsMaster'     => 1,
            'IsEnabled'    => 1,
            'IsHidden'     => 0
        ]);

        DB::table('AcademyStudents')->insert([
            'AcademyID'    => 'V1AC',
            'AccountID'    => $carlId,
        ]);
        DB::table('InstructorStudentsMulti')->insert([
            'InstructorID'         => $paulineId,
            'AccountID'            => $carlId,
            'IsVerified'           => 1,
            'StudentVerifiedAt'    => \Carbon\Carbon::now(),
            'InstructorVerifiedAt' => \Carbon\Carbon::now(),
        ]);

        DB::table('AcademyStudents')->insert([
            'AcademyID'    => 'SHYG',
            'AccountID'    => $carlId,
        ]);
        DB::table('InstructorStudentsMulti')->insert([
            'InstructorID'         => $shyId,
            'AccountID'            => $carlId,
            'IsVerified'           => 1,
            'StudentVerifiedAt'    => \Carbon\Carbon::now(),
            'InstructorVerifiedAt' => \Carbon\Carbon::now(),
        ]);


        foreach (range(1,25) as $index) {
            $studentId = DB::table('Accounts')->insertGetId([
                'FirstName'          => $faker->firstName(),
                'LastName'           => $faker->lastName(),
                'Email'              => $faker->safeEmail(),
                'PasswordHash'       => \Hash::make('password'),
            ]);

            DB::table('AcademyStudents')->insert([
                'AcademyID'    => 'SHYG',
                'AccountID'    => $studentId,
            ]);
            DB::table('InstructorStudentsMulti')->insert([
                'InstructorID'         => $shyId,
                'AccountID'            => $studentId,
                'IsVerified'           => 1,
                'StudentVerifiedAt'    => \Carbon\Carbon::now(),
                'InstructorVerifiedAt' => \Carbon\Carbon::now(),
            ]);
        }

        #$fakerJa = Faker::create('ja_JP');
        foreach (range(1,11) as $index) {

            $studentId = DB::table('Accounts')->insertGetId([
                'FirstName'          => $faker->firstName(),
                'LastName'           => $faker->lastName(),
                'Email'              => $faker->safeEmail(),
                'PasswordHash'       => \Hash::make('password'),
            ]);

            DB::table('AcademyStudents')->insert([
                'AcademyID'    => 'V1AC',
                'AccountID'    => $studentId,
            ]);
            DB::table('InstructorStudentsMulti')->insert([
                'InstructorID'         => $paulineId,
                'AccountID'            => $studentId,
                'IsVerified'           => 1,
                'StudentVerifiedAt'    => \Carbon\Carbon::now(),
                'InstructorVerifiedAt' => \Carbon\Carbon::now(),
            ]);
        }
    }
}
