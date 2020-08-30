<?php

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class UserSeeder extends BaseSeeder
{
    /**
     * @var HTTP download context for face image
     */
    private $faceContext;

    /**
     * Initialize the context
     */
    public function __construct()
    {
        parent::__construct();

        $headers = [
            'authority: thispersondoesnotexist.com',
            'pragma: no-cache',
            'cache-control: no-cache',
            'upgrade-insecure-requests: 1',
            'user-agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/75.0.3770.80 Safari/537.36',
            'accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3',
            'referer: https://thispersondoesnotexist.com/',
            'accept-encoding: gzip, deflate, br',
            'accept-language: en-US,en;q=0.9',
        ];
        $this->faceContext = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n ", $headers) . "\r\n",
            ]
        ]);
    }

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
                    $avatar = $this->downloadImage('https://thispersondoesnotexist.com/image', $this->faceContext, 'users', $user->uuid, false);
                } while ($avatar === false);
                $this->updateNode($user->uuid, [
                    'avatar' => $avatar,
                ]);
            }
        }
    }
}
