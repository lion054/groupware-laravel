<?php

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class UserSeeder extends BaseSeeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Let's truncate our existing records to start from scratch.
        Storage::disk('local')->deleteDirectory('users');

        $query = [
            'MATCH (u:User)',
            'DETACH DELETE u',
        ];
        $this->client->run(implode(' ', $query));

        $query = [
            'MATCH (d:Department)',
            'RETURN d',
        ];
        $result = $this->client->run(implode(' ', $query));
        $faker = \Faker\Factory::create();

        // And now, let's create a few users in our database:
        foreach ($result->getRecords() as $record) {
            $department = $record->get('d');
            $count = $faker->numberBetween(3, 5);
            for ($i = 0; $i < $count; $i++) {
                $user = $this->createNode('User', [
                    'name' => $faker->name,
                    'email' => $faker->email,
                    'password' => Hash::make('123456'),
                ]);
                do {
                    $avatar = $this->downloadFace('https://thispersondoesnotexist.com/image', $user->uuid);
                } while ($avatar === FALSE);
                $this->updateNode($user->uuid, [
                    'avatar' => $avatar,
                ]);
            }
        }
    }
}
