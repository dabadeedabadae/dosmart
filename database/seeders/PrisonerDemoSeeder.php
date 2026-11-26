<?php

namespace Database\Seeders;

use App\Models\Institution;
use App\Models\Prisoner;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Только для локальной демонстрации: php artisan db:seed --class=PrisonerDemoSeeder
 * Люди и ИИН вымышленные. Логин админки: demo / demo-dosmart. Код планшета: 20261120.
 */
class PrisonerDemoSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(app()->environment('local', 'testing'), 403, 'Демо-данные только для локального запуска.');

        $staff = User::updateOrCreate(['username' => 'demo'], [
            'name' => 'Демо-оператор', 'email' => 'demo@dosmart.local', 'password' => Hash::make('demo-dosmart'),
        ]);
        $institution = Institution::firstOrFail();
        $year = now('Asia/Almaty')->year;
        $institution->forceFill([
            'tablet_code' => '20261120',
            'parcel_mrp_rates' => [$year => config('pilot.mrp.'.$year, 4325)],
            'spending_limits_mrp' => [$year => ['strict' => 2, 'ordinary' => 6, 'eased' => 12]],
        ])->save();
        $previous = now('Asia/Almaty')->startOfMonth()->subMonth();

        // [ИИН, ФИО, отряд, условия, лимит, способ входа, секрет, баланс]
        $people = [
            ['000000000101', 'Демо Работающий Иванович', '7', 'ordinary', 'unlimited', null, null, 7500000],
            ['000000000102', 'Демо Неработающий Петрович', '7', 'ordinary', 'norm', 'pin', '000000000102', 5000000],
            ['000000000103', 'Демо Строгий Сергеевич', '3', 'strict', 'norm', 'question', 'Кокшетау', 1200000],
        ];
        foreach ($people as [$iin, $name, $squad, $condition, $mode, $method, $secret, $balance]) {
            $prisoner = Prisoner::firstOrNew(['iin' => $iin]);
            $prisoner->fill([
                'full_name' => $name, 'squad' => $squad, 'institution_id' => $institution->id, 'condition' => $condition,
                'spending_mode' => $mode, 'parcel_code' => $prisoner->parcel_code ?? strtoupper(bin2hex(random_bytes(12))),
            ]);
            $prisoner->resetRegistration();
            if ($method) {
                $prisoner->setCredentials($method, $secret, 'birth_place', $secret);
                $prisoner->registered_at = now();
            }
            // Лимит действует с прошлого месяца — видно, как переносится неиспользованный остаток.
            $prisoner->limit_started_on = $mode === 'unlimited' ? null : $previous->toDateString();
            $prisoner->save();
            if (! $prisoner->transfers()->exists()) {
                $prisoner->transfers()->create([
                    'application_number' => 'ДЕМО-'.substr($iin, -3), 'application_date' => now('Asia/Almaty')->toDateString(),
                    'amount_minor' => $balance, 'registered_by' => $staff->id,
                ])->forceFill(['status' => 'confirmed', 'payment_reference' => 'DEMO-'.$iin,
                    'confirmed_by' => $staff->id, 'confirmed_at' => now()])->save();
                $prisoner->forceFill(['balance_minor' => $balance])->save();
            }
            if ($mode !== 'unlimited' && ! $prisoner->limitPeriods()->where('period', $previous->format('Y-m'))->exists()) {
                $norm = $condition === 'strict' ? 2 : 6;
                $prisoner->limitPeriods()->create(['period' => $previous->format('Y-m'), 'limit_minor' => $norm * 432500]);
            }
        }
        $first = Prisoner::where('iin', '000000000101')->first();
        if (! $first->depositRequests()->exists()) {
            $first->depositRequests()->create(['sender_name' => 'Демо Иванова Мария', 'sender_phone' => '+77001112233', 'amount_minor' => 1000000]);
        }
    }
}
