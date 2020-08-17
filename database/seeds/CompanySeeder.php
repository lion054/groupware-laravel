<?php

class CompanySeeder extends BaseSeeder
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
            'MATCH (c:Company)',
            'DETACH DELETE c',
        ];
        $this->client->run(implode(' ', $query));

        $faker = \Faker\Factory::create();

        // And now, let's create a few companies in our database:
        for ($i = 0; $i < 3; $i++) {
            $this->createNode('Company', [
                'name' => $faker->company,
                'since' => $faker->dateTimeBetween('-10 years', '-2 years')->format(DateTimeInterface::RFC3339_EXTENDED),
            ]);
        }
    }
}
