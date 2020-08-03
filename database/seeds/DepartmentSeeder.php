<?php

use Ramsey\Uuid\Uuid;

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
        $label = $this->client->makeLabel('Department');
        foreach ($label->getNodes() as $department) {
            $relationships = $department->getRelationships();
            foreach ($relationships as $relationship)
                $relationship->delete();
            $department->delete();
        }

        $companies = $this->client->makeLabel('Company')->getNodes();
        $faker = \Faker\Factory::create();

        // And now, let's create a few companies in our database:
        foreach ($companies as $company) {
            for ($i = 0; $i < 10; $i++) {
                $department = $this->client->makeNode();
                $department->setProperty('uuid', Uuid::uuid4()->toString());
                $department->setProperty('name', $faker->company);
                $department->setProperty('capacity', $faker->numberBetween(5, 10));
                $department->save();
                $department->addLabels([$label]);

                $relationship = $department->relateTo($company, 'PART_OF');
                $relationship->save();
            }
        }
    }
}
