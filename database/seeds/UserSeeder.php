<?php

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
                $user = $this->createNode('User', [
                    'name' => $faker->name,
                    'email' => $faker->email,
                    'password' => Hash::make('123456'),
                ]);
                $this->createRelation($user->value('uuid'), $department->value('uuid'), 'WORKS_AT', [
                    'role' => $faker->boolean ? 'Master' : 'Engineer',
                    'took_at' => $faker->dateTimeBetween('-10 years', '-2 years')->format(DateTimeInterface::RFC3339_EXTENDED),
                    'left_at' => $faker->dateTimeBetween('-10 years', '-2 years')->format(DateTimeInterface::RFC3339_EXTENDED),
                ]);
            }
        }
    }
}
