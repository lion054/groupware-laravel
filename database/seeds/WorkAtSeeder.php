<?php

class WorkAtSeeder extends BaseSeeder
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
            'MATCH (u:User)-[r:WORK_AT]->(d:Department)',
            'DELETE r',
        ];
        $this->client->run(implode(' ', $query));

        $faker = \Faker\Factory::create();

        // Get the user list
        $query = [
            'MATCH (u:User)',
            'RETURN u',
        ];
        $result = $this->client->run(implode(' ', $query));
        $users = [];
        foreach ($result->getRecords() as $record) {
            $user = $record->get('u');
            $users[] = $user->value('uuid');
        }

        // Get the department list
        $query = [
            'MATCH (d:Department)',
            'RETURN d',
        ];
        $result = $this->client->run(implode(' ', $query));
        $departments = [];
        foreach ($result->getRecords() as $record) {
            $department = $record->get('d');
            $departments[] = $department->value('uuid');
        }

        foreach ($users as $userUuid) {
            $history = $faker->numberBetween(1, 5);
            for ($i = 0; $i < $history; $i++) {
                $departmentUuid = $faker->randomElement($departments);
                $data = [
                    'role' => $faker->boolean ? 'Master' : 'Engineer',
                    'took_at' => $faker->dateTimeBetween('-10 years', '-2 years')->format(DateTimeInterface::RFC3339_EXTENDED),
                    'left_at' => $faker->dateTimeBetween('-10 years', '-2 years')->format(DateTimeInterface::RFC3339_EXTENDED),
                ];
                if ($i == $history - 1) // The current job doesn't contain 'left_at'
                    unset($data['left_at']);
                $this->createRelation($userUuid, $departmentUuid, 'WORK_AT', );
            }
        }
    }
}
