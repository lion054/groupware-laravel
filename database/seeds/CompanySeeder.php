<?php

use Ramsey\Uuid\Uuid;

class CompanySeeder extends NeoSeeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Let's truncate our existing records to start from scratch.
        $this->client->run('MATCH (c:Company) DETACH DELETE c');

        $faker = \Faker\Factory::create();

        // And now, let's create a few companies in our database:
        for ($i = 0; $i < 10; $i++) {
            $since = $faker->dateTimeBetween('-10 years', '-2 years')->format(DateTimeInterface::RFC3339_EXTENDED);
            $query = 'CREATE (c:Company {
                uuid: {uuid},
                name: {name},
                since: DATETIME({since})
            })';
            $this->client->run($query, [
                'uuid' => Uuid::uuid4()->toString(),
                'name' => $faker->company,
                'since' => $since,
            ]);
        }
    }
}
