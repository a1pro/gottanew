<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Mail\CoachInvitationMail;
use App\Models\Coach\PendingCoachApplication;
use App\Models\Core\UserRole;
use App\Models\Coach\Coach;
use App\Models\Session\PasswordResetToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class CoachBulkUploadController extends BaseController
{
    public function upload(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file'],
        ]);

        $file = $request->file('file');
        $autoApprove = (int) $request->query('auto_approve', 0) === 1;

        $ext = strtolower($file->getClientOriginalExtension());

        // Try to parse spreadsheet if extension suggests Excel
        $rows = [];

        if (in_array($ext, ['xlsx', 'xls'], true)) {
            if (!class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
                return $this->error('Missing phpoffice/phpspreadsheet. Please run composer require phpoffice/phpspreadsheet', 500);
            }

            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getPathname());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, true);
        } else {
            // CSV or other text formats
            $handle = fopen($file->getPathname(), 'r');
            if ($handle === false) {
                return $this->error('Unable to open uploaded file', 500);
            }

            while (($data = fgetcsv($handle)) !== false) {
                $rows[] = $data;
            }

            fclose($handle);
        }

        if (empty($rows) || count($rows) < 2) {
            return $this->error('No data rows found in uploaded file', 422);
        }

        // Normalize header and data
        $header = array_map(fn($h) => trim((string)$h), is_array($rows[0]) ? $rows[0] : array_values($rows[0]));

        $dataRows = [];
        // rows may be associative when using PhpSpreadsheet -> keys A,B,C; convert to numeric-ordered arrays
        $startIndex = 1; // data starts at second row
        if (array_keys($rows)[0] !== 0) {
            // PhpSpreadsheet returns 1-indexed rows with letter keys; re-index
            $temp = [];
            foreach ($rows as $rIndex => $r) {
                $temp[] = array_values($r);
            }
            $rows = $temp;
            $header = array_map(fn($h) => trim((string)$h), $rows[0]);
        }

        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            // ensure numeric array
            if (!is_array($row)) continue;
            $assoc = [];
            for ($j = 0; $j < count($header); $j++) {
                $key = isset($header[$j]) ? $header[$j] : 'col_'.$j;
                $assoc[$key] = isset($row[$j]) ? trim((string)$row[$j]) : '';
            }
            $dataRows[] = ['row_number' => $i+1, 'data' => $assoc];
        }

        $summary = [
            'total_rows' => count($dataRows),
            'pending_count' => 0,
            'auto_approved_count' => 0,
            'users_created_count' => 0,
            'failed_count' => 0,
        ];

        $pending = [];
        $autoApproved = [];
        $usersCreated = [];
        $failed = [];

        foreach ($dataRows as $entry) {
            $rowNum = $entry['row_number'];
            $row = $entry['data'];

            $errors = [];

            $name = $row['name'] ?? '';
            $email = $row['email'] ?? '';
            $phone = $row['phone'] ?? null;
            $experience = $this->parseYearsExperience($row['experience'] ?? null);
            $specialties = $row['specialties'] ?? null;
            $message = $row['message'] ?? null;

            // Legal acceptance fields
            $accept_terms = $this->truthy($row['accept_terms'] ?? $row['accept_terms'] ?? '');
            $accept_privacy_policy = $this->truthy($row['accept_privacy_policy'] ?? '');
            $accept_coaching_disclaimer = $this->truthy($row['accept_coaching_disclaimer'] ?? '');
            $ack_coach_independence = $this->truthy($row['acknowledge_coach_independence'] ?? '');

            if (empty($name)) $errors[] = 'Missing name';
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email';
            if (!$accept_terms) $errors[] = 'accept_terms must be yes';
            if (!$accept_privacy_policy) $errors[] = 'accept_privacy_policy must be yes';
            if (!$accept_coaching_disclaimer) $errors[] = 'accept_coaching_disclaimer must be yes';
            if (!$ack_coach_independence) $errors[] = 'acknowledge_coach_independence must be yes';

            if (User::where('email', $email)->exists()) {
                $errors[] = 'Email already registered';
            }

            if (PendingCoachApplication::where('email', $email)->exists()) {
                $errors[] = 'Application already exists for this email';
            }

            if (!empty($errors)) {
                $failed[] = ['row' => $rowNum, 'email' => $email, 'errors' => $errors];
                $summary['failed_count']++;
                continue;
            }

            // Normalize specialties -> array
            $specArray = [];
            if (!empty($specialties)) {
                $specArray = preg_split('/\||,/', $specialties);
                $specArray = array_values(array_filter(array_map('trim', $specArray)));
            }

            DB::beginTransaction();
            try {
                $application = PendingCoachApplication::create([
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'experience' => $experience,
                    'specialties' => $specArray,
                    'message' => $message,
                    'status' => $autoApprove ? 'approved' : 'pending',
                ]);

                if ($autoApprove) {
                    // Create user and coach immediately
                    $plainPassword = Str::random(10);

                    $user = User::create([
                        'name' => $name,
                        'email' => $email,
                        'password' => Hash::make($plainPassword),
                    ]);

                    UserRole::create([
                        'user_id' => $user->id,
                        'role' => 'coach',
                    ]);

                    $coach = Coach::create([
                        'user_id' => $user->id,
                        'name' => $name,
                        'title' => 'Coach',
                        'bio' => $message ?? '',
                        'years_experience' => $experience ?? 1,
                        'specialties' => $specArray,
                        'notification_email' => $email,
                        'timezone' => 'UTC',
                        'is_active' => true,
                        'available_now' => false,
                        'similar_experiences' => $row['similar_experiences'] ?? '[]',
                        'hourly_rate_amount' => isset($row['hourly_rate_amount']) ? floatval($row['hourly_rate_amount']) : null,
                        'hourly_rate_currency' => $row['hourly_rate_currency'] ?? null,
                        'hourly_coin_cost' => isset($row['hourly_coin_cost']) ? floatval($row['hourly_coin_cost']) : null,
                        'booking_buffer_minutes' => isset($row['booking_buffer_minutes']) ? intval($row['booking_buffer_minutes']) : null,
                        'max_session_duration' => isset($row['max_session_duration']) ? intval($row['max_session_duration']) : null,
                        'min_session_duration' => isset($row['min_session_duration']) ? intval($row['min_session_duration']) : null,
                        'immediate_availability' => $this->truthy($row['immediate_availability'] ?? ''),
                        'response_preference_minutes' => isset($row['response_preference_minutes']) ? intval($row['response_preference_minutes']) : null,
                    ]);

                    // Create onboarding token so coach can set password / onboard
                    $plainToken = hash('sha256', Str::random(64));
                    PasswordResetToken::where('email', $email)->delete();
                    PasswordResetToken::create([
                        'email' => $email,
                        'token' => $plainToken,
                        'created_at' => now(),
                    ]);

                    $passwordSent = false;
                    try {
                        Mail::to($email)->send(new CoachInvitationMail($email, $plainToken));
                        $passwordSent = true;
                    } catch (\Throwable $e) {
                        // swallow mail errors but record false
                        $passwordSent = false;
                    }

                    $autoApproved[] = [
                        'row' => $rowNum,
                        'email' => $email,
                        'application_id' => $application->id,
                        'auto_approved' => true,
                        'generated_password' => $plainPassword,
                    ];

                    $usersCreated[] = [
                        'row' => $rowNum,
                        'email' => $email,
                        'user_id' => $user->id,
                        'application_id' => $application->id,
                        'password_sent' => $passwordSent,
                    ];

                    $summary['auto_approved_count']++;
                    $summary['users_created_count']++;
                } else {
                    $pending[] = [
                        'row' => $rowNum,
                        'email' => $email,
                        'application_id' => $application->id,
                        'auto_approved' => false,
                        'generated_password' => null,
                    ];

                    $summary['pending_count']++;
                }

                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                $failed[] = ['row' => $rowNum, 'email' => $email, 'errors' => [$e->getMessage()]];
                $summary['failed_count']++;
            }
        }

        $result = [
            'summary' => $summary,
            'pending_applications' => $pending,
            'auto_approved_applications' => $autoApproved,
            'users_created' => $usersCreated,
            'failed_rows' => $failed,
        ];

        return $this->success($result, 'Bulk upload processed');
    }

    private function truthy($value): bool
    {
        if (is_bool($value)) return $value;
        $v = strtolower(trim((string)$value));
        return in_array($v, ['1', 'yes', 'y', 'true', 't'], true);
    }

    private function parseYearsExperience($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        // Pulls the first number out of strings like "5 years", "5+", "5.5 yrs", etc.
        if (preg_match('/(\d+(\.\d+)?)/', (string) $value, $matches)) {
            return (int) round((float) $matches[1]);
        }

        return null;
    }
}
