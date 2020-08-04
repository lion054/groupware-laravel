<?php

class DepartmentSeeder extends NeoSeeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Let's truncate our existing records to start from scratch.
        $this->client->run('MATCH (d:Department) DETACH DELETE d');

        $result = $this->client->run('MATCH (c:Company) RETURN c');
        $faker = \Faker\Factory::create();

        // And now, let's create a few departments in our database:
        foreach ($result->getRecords() as $record) {
            $company = $record->get('c');
            for ($i = 0; $i < 10; $i++) {
                $uuid = $this->getUuidToCreate('Company');
                $this->client->run('CREATE (d:Department) SET d += {infos}', [
                    'infos' => [
                        'uuid' => $uuid,
                        'name' => $faker->company,
                        'capacity' => $faker->numberBetween(5, 10),
                    ]
                ]);
                $query = [
                    'MATCH (d:Department),(c:Company)',
                    'WHERE d.uuid = {d_uuid} AND c.uuid = {c_uuid}',
                    'CREATE (d)-[r:PART_OF]->(c)',
                    'RETURN r',
                ];
                $this->client->run(implode(' ', $query), [
                    'd_uuid' => $uuid,
                    'c_uuid' => $company->value('uuid'),
                ]);
            }
        }
    }
}
