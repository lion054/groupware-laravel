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
        $label = $this->client->makeLabel('Company');
        foreach ($label->getNodes() as $company) {
            $relationships = $company->getRelationships();
            foreach ($relationships as $relationship)
                $relationship->delete();
            $company->delete();
        }

        $faker = \Faker\Factory::create();

        // And now, let's create a few companies in our database:
        for ($i = 0; $i < 10; $i++) {
            $company = $this->client->makeNode();
            $company->setProperty('uuid', Uuid::uuid4()->toString());
            $company->setProperty('name', $faker->company);
            $company->setProperty('since', $faker->numberBetween(1900, 2100));
            $company->save();
            $company->addLabels([$label]); // Add a label, after saving node
        }
    }
}
