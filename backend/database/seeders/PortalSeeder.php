<?php

namespace Database\Seeders;

use App\Models\Child;
use App\Models\Compliance;
use App\Models\Contract;
use App\Models\Department;
use App\Models\LeaveBalance;
use App\Models\Training;
use App\Models\TrainingAssignment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PortalSeeder extends Seeder
{
    public function run(): void
    {
        $program = Department::create(['name' => 'Program']);
        Department::create(['name' => 'Finance']);
        Department::create(['name' => 'HR']);
        Department::create(['name' => 'Operations']);

        /* ---- Users ---- */
        $admin = User::create([
            'staff_id' => 'HR-0001',
            'first_name' => 'Sokha',
            'last_name' => 'Chan',
            'email' => 'admin@portal.test',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'position' => 'HR Manager',
            'department_id' => $program->id,
        ]);

        $staff = User::create([
            'staff_id' => 'ST-00123',
            'first_name' => 'Borey',
            'last_name' => 'Penh',
            'name_kh' => 'បុរេយ ភេន',
            'email' => 'staff@portal.test',
            'password' => Hash::make('password'),
            'role' => 'staff',
            'position' => 'Program Officer',
            'department_id' => $program->id,
            'phone' => '+855 12 849 201',
            'address' => '#42B, St. 310, Phnom Penh',
            'dob' => '1992-08-15',
            'gender' => 'Male',
            'pob' => 'Battambang Province, Cambodia',
            'nationality' => 'Khmer',
            'nid' => '010892415',
            'marital' => 'Married',
        ]);

        Child::create(['user_id' => $staff->id, 'name' => 'Dara Penh', 'dob' => '2020-03-02', 'gender' => 'Male']);
        Child::create(['user_id' => $staff->id, 'name' => 'Srey Penh', 'dob' => '2023-11-20', 'gender' => 'Female']);

        /* ---- Compliances (dynamic) ---- */
        foreach ([
            'Code of Conduct' => 'I confirm that I have read and understood the Code of Conduct policy.',
            'Child Safeguarding' => 'I confirm that I have read and understood the Child Safeguarding policy.',
            'Adult Safeguarding (PSEAH)' => 'I confirm that I have read and understood the Adult Safeguarding (PSEAH) policy.',
            'Conflict of Interest' => 'I declare that I have no undisclosed conflict of interest.',
            'Anti-Fraud' => 'I confirm that I have read and understood the Anti-Fraud policy.',
        ] as $title => $desc) {
            Compliance::create(['title' => $title, 'description' => $desc]);
        }

        /* ---- Training with sections + quiz ---- */
        $training = Training::create([
            'title' => 'Child Safeguarding',
            'description' => 'Essential training on child protection policies and practices.',
            'sections' => [
                ['title' => '1. Introduction', 'body' => 'Why child safeguarding matters and who is covered by this policy.'],
                ['title' => '2. Policy', 'body' => 'The four core commitments and reporting obligations.'],
                ['title' => '3. Video', 'body' => 'Watch the orientation video (placeholder link).'],
                ['title' => '4. Quiz', 'body' => 'Answer the questions to check your understanding.'],
                ['title' => '5. Final Assessment', 'body' => 'Score at least 80% to complete this training.'],
            ],
            'quiz' => [
                ['question' => 'How many core commitments does the safeguarding policy have?', 'options' => ['2', '4', '6', '8'], 'answer' => 1],
                ['question' => 'Who must report a safeguarding concern?', 'options' => ['Only managers', 'Only HR', 'Everyone', 'Only the person involved'], 'answer' => 2],
                ['question' => 'When should a concern be reported?', 'options' => ['Within a week', 'Immediately', 'At the next meeting', 'Never — handle privately'], 'answer' => 1],
                ['question' => 'Safeguarding applies to…', 'options' => ['Program staff only', 'Office staff only', 'All staff and volunteers', 'Only field visits'], 'answer' => 2],
                ['question' => 'What is the pass score for this training?', 'options' => ['50%', '60%', '80%', '100%'], 'answer' => 2],
            ],
            'pass_score' => 80,
        ]);

        Training::create([
            'title' => 'Anti-Fraud',
            'description' => 'Recognizing and reporting fraud, bribery and corruption.',
            'sections' => [
                ['title' => '1. What is fraud?', 'body' => 'Definitions and examples relevant to our operations.'],
                ['title' => '2. Red flags', 'body' => 'Warning signs in procurement, expenses and reporting.'],
                ['title' => '3. Quiz', 'body' => 'Check your understanding.'],
            ],
            'quiz' => [
                ['question' => 'Which is a fraud red flag?', 'options' => ['Clear invoices', 'Split purchase orders', 'Documented approvals', 'Competitive quotes'], 'answer' => 1],
                ['question' => 'Who can report suspected fraud?', 'options' => ['Finance only', 'Managers only', 'Anyone', 'Auditors only'], 'answer' => 2],
            ],
            'pass_score' => 80,
        ]);

        TrainingAssignment::create([
            'training_id' => $training->id,
            'user_id' => $staff->id,
            'due_date' => now()->addDays(15),
        ]);

        /* ---- Contract ---- */
        Contract::create([
            'user_id' => $staff->id,
            'type' => 'Probationary',
            'position' => 'Program Assistant',
            'department' => 'Program',
            'start_date' => '2026-01-01',
            'end_date' => '2026-03-31',
            'salary' => 450,
            'status' => 'Completed',
        ]);
        Contract::create([
            'user_id' => $staff->id,
            'type' => 'Full-Time',
            'position' => 'Program Officer',
            'department' => 'Program',
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
            'salary' => 700,
            'status' => 'Active',
        ]);

        /* ---- Leave balances ---- */
        LeaveBalance::create(['user_id' => $staff->id, 'type' => 'Annual', 'entitled' => 15, 'used' => 3]);
        LeaveBalance::create(['user_id' => $staff->id, 'type' => 'Sick', 'entitled' => 10, 'used' => 5]);
        LeaveBalance::create(['user_id' => $staff->id, 'type' => 'Other', 'entitled' => 5, 'used' => 2]);

        /* ---- Reference vehicle ---- */
        \App\Models\Vehicle::create(['name' => 'Car-001']);
        \App\Models\Vehicle::create(['name' => 'Motorbike-001']);
    }
}
