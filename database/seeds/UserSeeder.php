<?php

use Ramsey\Uuid\Uuid;

class UserSeeder extends NeoSeeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Let's truncate our existing records to start from scratch.
        $this->client->run('MATCH (u:User) DETACH DELETE u');

        $result = $this->client->run('MATCH (d:Department) RETURN d');
        $faker = \Faker\Factory::create();

        // And now, let's create a few users in our database:
        foreach ($result->getRecords() as $record) {
            $department = $record->get('d');
            $count = $faker->numberBetween(5, 10);
            for ($i = 0; $i < $count; $i++) {
                $uuid = Uuid::uuid4()->toString();
                $this->client->run('CREATE (u:User) SET u += {infos}', [
                    'infos' => [
                        'uuid' => $uuid,
                        'name' => $faker->name,
                        'email' => $faker->email,
                        'password' => Hash::make('123456'),
                    ]
                ]);

                $since = $faker->dateTimeBetween('-10 years', '-2 years')->format(DateTimeInterface::RFC3339_EXTENDED);
                $taken_at = $faker->dateTimeBetween('-10 years', '-2 years')->format(DateTimeInterface::RFC3339_EXTENDED);
                $left_at = $faker->dateTimeBetween('-10 years', '-2 years')->format(DateTimeInterface::RFC3339_EXTENDED);
                $query = 'MATCH (u:User),(d:Department)
                    WHERE u.uuid = {u_uuid} AND d.uuid = {d_uuid}
                    CREATE (u)-[r:WORKING_AT{
                        position: {position},
                        taken_at: DATETIME({taken_at}),
                        left_at: DATETIME({left_at})
                    }]->(d)
                    RETURN r';
                $this->client->run($query, [
                    'u_uuid' => $uuid,
                    'd_uuid' => $department->value('uuid'),
                    'position' => $faker->boolean ? 'Master' : 'Engineer',
                    'taken_at' => $taken_at,
                    'left_at' => $left_at,
                ]);
            }
        }
    }
}
