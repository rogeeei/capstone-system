<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Services as Service; 


class ServicesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
     public function run()
    {
       $services = [
    ['name' => 'General Consultation', 'icon' => 'fas fa-user-md', 'description' => 'Provides general medical check-ups and health advice.'],
    ['name' => 'Dental Check-up', 'icon' => 'fas fa-tooth', 'description' => 'Regular dental check-ups and oral health services.'],
    ['name' => 'Vaccination', 'icon' => 'fas fa-syringe', 'description' => 'Immunization against various diseases.'],
    ['name' => 'Family Planning', 'icon' => 'fas fa-baby', 'description' => 'Consultations and contraceptive options for family planning.'],
    ['name' => 'Emergency Medical Care', 'icon' => 'fas fa-ambulance', 'description' => 'Immediate treatment for medical emergencies.'],
    ['name' => 'Mental Health Counseling', 'icon' => 'fas fa-brain', 'description' => 'Psychological counseling and mental health support.'],
    ['name' => 'Prenatal Care', 'icon' => 'fas fa-heartbeat', 'description' => 'Medical care for pregnant women to ensure a healthy pregnancy.'],
    ['name' => 'Postnatal Care', 'icon' => 'fas fa-stethoscope', 'description' => 'Health care for mothers and newborns after delivery.'],
    ['name' => 'Child Growth Monitoring', 'icon' => 'fas fa-child', 'description' => 'Tracking and ensuring proper growth in children.'],
    ['name' => 'Senior Citizen Health', 'icon' => 'fas fa-user-plus', 'description' => 'Health services and check-ups for senior citizens.'],
    ['name' => 'Tuberculosis Screening', 'icon' => 'fas fa-lungs', 'description' => 'Screening and diagnosis for tuberculosis.'],
    ['name' => 'HIV/AIDS Counseling', 'icon' => 'fas fa-ribbon', 'description' => 'Counseling and support for HIV/AIDS patients.'],
    ['name' => 'Diabetes Screening', 'icon' => 'fas fa-vial', 'description' => 'Testing and monitoring for diabetes.'],
    ['name' => 'Hypertension Screening', 'icon' => 'fas fa-heart', 'description' => 'Blood pressure monitoring and management.'],
    ['name' => 'Nutrition Counseling', 'icon' => 'fas fa-apple-alt', 'description' => 'Dietary advice for a healthier lifestyle.'],
    ['name' => 'Deworming Program', 'icon' => 'fas fa-pills', 'description' => 'Treatment for intestinal parasites.'],
    ['name' => 'Malaria Prevention', 'icon' => 'fas fa-mosquito', 'description' => 'Prevention and treatment of malaria.'],
    ['name' => 'Immunization', 'icon' => 'fas fa-syringe', 'description' => 'Protection against infectious diseases.'],
];

foreach ($services as $service) {
    Service::updateOrCreate(['name' => $service['name']], $service);
}

    }
}
