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
        $query = [
            'MATCH (d:Department)',
            'DETACH DELETE d',
        ];
        $this->client->run(implode(' ', $query));

        $query = [
            'MATCH (c:Company)',
            'RETURN c',
        ];
        $result = $this->client->run(implode(' ', $query));
        $faker = \Faker\Factory::create();

        // And now, let's create a few departments in our database:
        foreach ($result->getRecords() as $record) {
            $company = $record->get('c');
            for ($i = 0; $i < 3; $i++) {
                $department = $this->createNode('Department', [
                    'name' => $faker->company,
                    'capacity' => $faker->numberBetween(5, 10),
                ]);
                $uuid = $department->value('uuid');
                $this->createRelation($uuid, $company->value('uuid'), 'ASSIGNED_TO');
                for ($j = 0; $j < 3; $j++) {
                    $department = $this->createNode('Department', [
                        'name' => $faker->company,
                        'capacity' => $faker->numberBetween(5, 10),
                    ]);
                    $this->createRelation($department->value('uuid'), $uuid, 'ASSIGNED_TO');
                }
            }
        }
    }
}
