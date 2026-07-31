<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Academic\Models\AcademicYear;
use App\Modules\Academic\Models\SchoolClass;
use App\Modules\Attendance\Models\Holiday;
use App\Modules\Certificate\Models\AdmitCard;
use App\Modules\Certificate\Models\Testimonial;
use App\Modules\Certificate\Models\TestimonialTemplate;
use App\Modules\Examination\Models\Exam;
use App\Modules\Examination\Models\ExamSeating;
use App\Modules\IdCard\Models\IdCardBatch;
use App\Modules\IdCard\Models\IdCardTemplate;
use App\Modules\Loan\Models\LoanSchedule;
use App\Modules\Loan\Models\StaffLoan;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Models\Refund;
use App\Modules\Payroll\Models\PayrollEntry;
use App\Modules\Payroll\Models\PayrollRun;
use App\Modules\Payroll\Models\StaffSalaryValue;
use App\Modules\School\Models\School;
use App\Modules\Sms\Models\SmsBatch;
use App\Modules\Sms\Models\SmsLog;
use App\Modules\Staff\Models\Staff;
use App\Modules\Student\Models\Student;
use App\Modules\Student\Models\StudentAcademic;
use App\Modules\Student\Models\StudentGuardian;
use App\Modules\Student\Models\TransferCertificate;
use App\Modules\Student\Models\TransferCertificateTemplate;
use App\Modules\Website\Models\ContactMessage;
use Illuminate\Database\Seeder;

/**
 * Demo data for the modules DemoOperationsSeeder/DemoOptionalSeeder never
 * touch: Certificate (admit cards, testimonials, transfer certificate),
 * IdCard (templates + a completed batch each for student/staff), Sms
 * (a completed due-reminder batch with per-recipient logs), Payroll (one
 * approved run with entries built from the salary components DemoOptionalSeeder
 * already assigned), Loan (one approved staff loan + its repayment
 * schedule), Refund (a partial refund against an existing payment), Holidays,
 * and public Contact messages. Without this, those admin screens are reachable
 * but permanently empty on a fresh install — nothing exercises them. Run last.
 */
class DemoCompletionSeeder extends Seeder
{
    public function run(): void
    {
        $school = School::first();
        if (! $school) {
            return;
        }
        $sid = $school->id;
        $admin = User::where('school_id', $sid)->first();
        if (! $admin) {
            return;
        }
        $adminId = $admin->id;
        $year = AcademicYear::where('school_id', $sid)->where('is_current', true)->first();

        // ── Certificate: admit cards for the already-seated Half Yearly exam ──
        $exam = Exam::where('school_id', $sid)->where('title', 'Half Yearly Examination')->first();
        if ($exam) {
            $seated = ExamSeating::where('school_id', $sid)->where('exam_id', $exam->id)->get();
            foreach ($seated as $seat) {
                AdmitCard::firstOrCreate(
                    ['school_id' => $sid, 'student_id' => $seat->student_id, 'exam_id' => $exam->id],
                    ['generated_at' => now(), 'generated_by' => $adminId],
                );
            }
        }

        // ── Certificate: testimonials for two Class 8 students ──────────────
        $template = TestimonialTemplate::firstOrCreate(
            ['school_id' => $sid, 'name' => 'Default Testimonial Template'],
            [
                'template_body' => '<p>This is to certify that <strong>{{student_name}}</strong> '
                    .'(Admission No. {{admission_number}}) was a student of {{class}} at {{school_name}}, '
                    .'with a conduct record of: {{conduct_remark}}. Result: {{grade}} (GPA {{gpa}}, '
                    .'{{percentage}}%). Attendance: {{attendance_percentage}}%.</p><p>Issued on {{issued_date}}.</p>',
                'footer_text' => 'This certificate is issued on request and is valid for official use.',
                'signatory_name' => 'Abdul Karim',
                'signatory_designation' => 'Head Teacher',
                'is_default' => true,
            ],
        );

        $class8 = SchoolClass::where('school_id', $sid)->where('name', 'Class 8')->first();
        if ($year && $class8) {
            $class8Students = StudentAcademic::where('school_id', $sid)->where('class_id', $class8->id)
                ->where('academic_year_id', $year->id)->where('is_current', true)
                ->orderBy('roll_number')->take(2)->pluck('student_id');

            foreach ($class8Students as $i => $studentId) {
                Testimonial::firstOrCreate(
                    ['school_id' => $sid, 'testimonial_number' => 'TST/'.$year->year.'/'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT)],
                    [
                        'student_id' => $studentId,
                        'template_id' => $template->id,
                        'exam_id' => $exam?->id,
                        'issued_date' => now()->toDateString(),
                        'issued_by' => $adminId,
                        'conduct_remark' => 'Good conduct, regular in attendance and studies.',
                        'attendance_from' => now()->startOfYear()->toDateString(),
                        'attendance_to' => now()->toDateString(),
                        'status' => 'issued',
                    ],
                );
            }
        }

        // ── Student: transfer certificate template + one issued TC ──────────
        $tcTemplate = TransferCertificateTemplate::firstOrCreate(
            ['school_id' => $sid, 'name' => 'Default TC Template'],
            [
                'template_body' => '<p>This is to certify that <strong>{{student_name}}</strong> was a bona fide '
                    .'student of {{class}} at {{school_name}}. Transfer Certificate No. {{tc_number}} is issued on '
                    .'{{issued_date}} for the following reason: {{reason}}.</p>',
                'footer_text' => 'Issued by the school administration.',
                'signatory_name' => 'Abdul Karim',
                'signatory_designation' => 'Head Teacher',
                'is_default' => true,
            ],
        );

        $class10 = SchoolClass::where('school_id', $sid)->where('name', 'Class 10')->first();
        if ($year && $class10) {
            $leavingStudentId = StudentAcademic::where('school_id', $sid)->where('class_id', $class10->id)
                ->where('academic_year_id', $year->id)->where('is_current', true)
                ->orderBy('roll_number')->value('student_id');

            if ($leavingStudentId) {
                TransferCertificate::firstOrCreate(
                    ['school_id' => $sid, 'tc_number' => 'TC/'.$year->year.'/001'],
                    [
                        'student_id' => $leavingStudentId,
                        'template_id' => $tcTemplate->id,
                        'issued_date' => now()->toDateString(),
                        'issued_by' => $adminId,
                        'reason' => 'completion',
                        'status' => 'issued',
                    ],
                );
            }
        }

        // ── IdCard: one student template + one staff template, each with a
        //    completed batch ───────────────────────────────────────────────
        $studentIdTemplate = IdCardTemplate::firstOrCreate(
            ['school_id' => $sid, 'type' => 'student', 'name' => 'Standard Student ID'],
            [
                'layout' => 'horizontal_classic', 'background_color' => '#ffffff', 'accent_color' => '#1a56db',
                'font' => 'sans',
                'visible_fields' => ['name', 'student_id', 'class', 'section', 'photo', 'blood_group'],
                'is_default' => true,
            ],
        );
        $staffIdTemplate = IdCardTemplate::firstOrCreate(
            ['school_id' => $sid, 'type' => 'staff', 'name' => 'Standard Staff ID'],
            [
                'layout' => 'vertical', 'background_color' => '#ffffff', 'accent_color' => '#1a56db',
                'font' => 'sans',
                'visible_fields' => ['name', 'designation', 'department', 'staff_id', 'photo'],
                'is_default' => true,
            ],
        );

        if ($class8) {
            $class8Count = StudentAcademic::where('school_id', $sid)->where('class_id', $class8->id)
                ->where('is_current', true)->count();
            IdCardBatch::firstOrCreate(
                ['school_id' => $sid, 'type' => 'student', 'template_id' => $studentIdTemplate->id, 'scope' => 'class', 'class_id' => $class8->id],
                ['total_count' => $class8Count, 'status' => 'completed', 'requested_by' => $adminId, 'generated_at' => now()],
            );
        }

        $activeStaffCount = Staff::where('school_id', $sid)->where('status', 'active')->count();
        IdCardBatch::firstOrCreate(
            ['school_id' => $sid, 'type' => 'staff', 'template_id' => $staffIdTemplate->id, 'scope' => 'all'],
            ['total_count' => $activeStaffCount, 'status' => 'completed', 'requested_by' => $adminId, 'generated_at' => now()],
        );

        // ── Sms: one completed due-reminder batch with per-recipient logs ───
        $smsBatch = SmsBatch::firstOrCreate(
            ['school_id' => $sid, 'purpose' => 'due_reminder', 'scope' => 'all', 'academic_year_id' => $year?->id],
            ['status' => 'completed', 'total_count' => 3, 'requested_by' => $adminId, 'completed_at' => now()],
        );

        $reminderTargets = Student::where('school_id', $sid)->orderBy('id')->take(3)->get();
        foreach ($reminderTargets as $i => $st) {
            $guardian = StudentGuardian::where('school_id', $sid)->where('student_id', $st->id)->first();
            $failed = $i === 2; // last one demonstrates a failed send (no phone on file)
            SmsLog::firstOrCreate(
                ['school_id' => $sid, 'batch_id' => $smsBatch->id, 'student_id' => $st->id],
                [
                    'guardian_id' => $guardian?->id,
                    'recipient_phone' => $failed ? null : $guardian?->phone,
                    'body' => "Dear Guardian, {$st->name}'s tuition fee for this month is due. Please pay at your earliest convenience. - {$school->name}",
                    'encoding' => 'gsm7',
                    'segment_count' => 1,
                    'cost' => null, // school.sms_cost_per_segment is not configured in demo data
                    'status' => $failed ? 'failed' : 'sent',
                    'error_message' => $failed ? 'No guardian phone on file' : null,
                    'purpose' => 'due_reminder',
                    'sent_by' => $adminId,
                    'sent_at' => $failed ? null : now(),
                ],
            );
        }

        // ── Payroll: one approved run this month, entries from the salary
        //    values DemoOptionalSeeder already assigned to teaching staff ───
        $curMonth = (int) date('n');
        $curYear = (int) date('Y');
        $payrollRun = PayrollRun::firstOrCreate(
            ['school_id' => $sid, 'month' => $curMonth, 'year' => $curYear],
            [
                'status' => 'approved', 'processed_by' => $adminId, 'processed_at' => now(),
                'approved_by' => $adminId, 'approved_at' => now(),
            ],
        );

        $teachingStaff = Staff::where('school_id', $sid)->whereNotNull('subject_id')->get();
        foreach ($teachingStaff as $staff) {
            $values = StaffSalaryValue::where('school_id', $sid)->where('staff_id', $staff->id)->with('component')->get();
            if ($values->isEmpty()) {
                continue;
            }
            $gross = 0.0;
            $deductions = 0.0;
            $breakdown = [];
            foreach ($values as $v) {
                $type = $v->component->component_type;
                $amount = (float) $v->amount;
                if ($type === 'earning') {
                    $gross += $amount;
                } else {
                    $deductions += $amount;
                }
                $breakdown[] = ['label' => $v->component->name, 'type' => $type, 'amount' => $amount];
            }

            PayrollEntry::firstOrCreate(
                ['school_id' => $sid, 'payroll_run_id' => $payrollRun->id, 'staff_id' => $staff->id],
                [
                    'gross_salary' => $gross, 'total_deductions' => $deductions,
                    'net_salary' => $gross - $deductions, 'breakdown' => $breakdown,
                ],
            );
        }

        // ── Loan: one approved staff loan + its repayment schedule ──────────
        $librarian = Staff::where('school_id', $sid)->where('name', 'Nurul Islam')->first();
        if ($librarian) {
            $loan = StaffLoan::firstOrCreate(
                ['school_id' => $sid, 'staff_id' => $librarian->id, 'reason' => 'Personal financial need'],
                [
                    'requested_amount' => 30000, 'installment_count' => 6,
                    'start_date' => now()->addMonthNoOverflow()->startOfMonth()->toDateString(),
                    'status' => 'approved', 'requested_by' => $adminId,
                    'approved_by' => $adminId, 'approved_at' => now(),
                ],
            );
            $installment = round(30000 / 6, 2);
            for ($n = 1; $n <= 6; $n++) {
                LoanSchedule::firstOrCreate(
                    ['school_id' => $sid, 'staff_loan_id' => $loan->id, 'installment_number' => $n],
                    [
                        'due_date' => now()->addMonthsNoOverflow($n)->startOfMonth()->toDateString(),
                        'amount' => $installment, 'is_paid' => false,
                    ],
                );
            }
        }

        // ── Refund: a partial refund against an existing tuition payment ────
        $payment = Payment::where('school_id', $sid)->where('is_reversed', false)->orderBy('id')->first();
        if ($payment) {
            $refundAmount = min(200.0, (float) $payment->amount);
            Refund::firstOrCreate(
                ['school_id' => $sid, 'payment_id' => $payment->id],
                [
                    'amount' => $refundAmount, 'processing_fee' => 0, 'net_refund' => $refundAmount,
                    'method' => 'cash', 'status' => 'completed', 'gateway_ref' => null,
                    'requested_by' => $adminId, 'processed_by' => $adminId, 'processed_at' => now(),
                    'note' => 'Overpayment refund',
                ],
            );
        }

        // ── Holidays for the current year ────────────────────────────────
        $holidayYear = $year?->year ?? $curYear;
        foreach ([
            ['name' => 'International Mother Language Day', 'date' => "{$holidayYear}-02-21", 'type' => 'government'],
            ['name' => 'Independence Day', 'date' => "{$holidayYear}-03-26", 'type' => 'government'],
            ['name' => 'Eid-ul-Fitr Holidays', 'date' => "{$holidayYear}-04-11", 'type' => 'religious'],
            ['name' => 'Victory Day', 'date' => "{$holidayYear}-12-16", 'type' => 'government'],
            ['name' => 'Winter Break', 'date' => "{$holidayYear}-12-25", 'type' => 'closure'],
        ] as $h) {
            Holiday::firstOrCreate(
                ['school_id' => $sid, 'date' => $h['date']],
                ['name' => $h['name'], 'type' => $h['type'], 'created_by' => $adminId],
            );
        }

        // ── Public contact messages ──────────────────────────────────────
        foreach ([
            ['name' => 'Shahin Mia', 'email' => 'shahin.mia@example.com', 'phone' => '01711000111', 'subject' => 'Admission query', 'message' => 'I would like to know the admission process for Class 6. Please advise on documents needed.', 'is_read' => true],
            ['name' => 'Farzana Yesmin', 'email' => 'farzana.y@example.com', 'phone' => '01811222333', 'subject' => 'Transport route', 'message' => 'Does the school bus cover the Mirpur area? My child would need pickup from there.', 'is_read' => false],
            ['name' => 'Kamal Pasha', 'email' => null, 'phone' => '01911444555', 'subject' => 'Fee structure', 'message' => 'Could you share the monthly fee structure for the upcoming academic year?', 'is_read' => false],
        ] as $cm) {
            ContactMessage::firstOrCreate(
                ['school_id' => $sid, 'email' => $cm['email'], 'subject' => $cm['subject']],
                [
                    'name' => $cm['name'], 'phone' => $cm['phone'], 'message' => $cm['message'],
                    'is_read' => $cm['is_read'],
                ],
            );
        }
    }
}
