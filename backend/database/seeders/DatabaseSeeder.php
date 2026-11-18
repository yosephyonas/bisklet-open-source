<?php

namespace Database\Seeders;

use App\Enums\BikeStatus;
use App\Enums\UserRole;
use App\Models\Bike;
use App\Models\FydaVerification;
use App\Models\Station;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException('Demo identities must never be seeded in production');
        }

        // Set BISKLET_DEMO_PASSWORD in local development if test logins are needed.
        // Otherwise a disposable random password is generated for each seed run.
        $demoPassword = (string) env('BISKLET_DEMO_PASSWORD', '');
        if (strlen($demoPassword) < 16) {
            $demoPassword = bin2hex(random_bytes(24));
        }

        $now = now();

        // Create or update the admin user (idempotent)
        $admin = User::updateOrCreate(
            ['email' => 'admin@example.test'],
            [
                'national_id' => '0000000000000',
                'first_name' => 'System',
                'last_name' => 'Admin',
                'father_name' => 'Bisklet',
                'email' => 'admin@example.test',
                'phone' => '+251900000000',
                'role' => UserRole::Admin,
                'password' => Hash::make($demoPassword),
                'fyda_verified_at' => $now,
                'metadata' => ['seeded' => true],
            ]
        );

        // Ensure FydaVerification exists for the admin
        FydaVerification::updateOrCreate(
            ['user_id' => $admin->id],
            [
                'national_id' => $admin->national_id,
                'status' => 'verified',
                'response_payload' => [
                    'first_name' => $admin->first_name,
                    'last_name' => $admin->last_name,
                ],
                'verified_at' => $now,
            ]
        );

        $ridersData = [
            [
                'national_id' => '1000000000001',
                'first_name' => 'Sara',
                'last_name' => 'Abebe',
                'father_name' => 'Tadesse',
                'email' => 'sara.abebe@example.test',
                'phone' => '+251911000111',
                'date_of_birth' => '1992-04-10',
                'metadata' => ['preferred_language' => 'en'],
            ],
            [
                'national_id' => '1000000000002',
                'first_name' => 'Yonas',
                'last_name' => 'Mekonnen',
                'father_name' => 'Getachew',
                'email' => 'yonas.mekonnen@example.test',
                'phone' => '+251912000222',
                'date_of_birth' => '1989-09-02',
                'metadata' => ['preferred_language' => 'am'],
            ],
            [
                'national_id' => '1000000000003',
                'first_name' => 'Hana',
                'last_name' => 'Wolde',
                'father_name' => 'Bekele',
                'email' => 'hana.wolde@example.test',
                'phone' => '+251913000333',
                'date_of_birth' => '1995-01-15',
                'metadata' => ['preferred_language' => 'en'],
            ],
            [
                'national_id' => '1000000000004',
                'first_name' => 'Abel',
                'last_name' => 'Tesfaye',
                'father_name' => 'Haile',
                'email' => 'abel.tesfaye@example.test',
                'phone' => '+251914000444',
                'date_of_birth' => '1990-12-05',
                'metadata' => ['preferred_language' => 'om'],
            ],
            [
                'national_id' => '1000000000005',
                'first_name' => 'Liya',
                'last_name' => 'Desta',
                'father_name' => 'Kebede',
                'email' => 'liya.desta@example.test',
                'phone' => '+251915000555',
                'date_of_birth' => '1997-07-22',
                'metadata' => ['preferred_language' => 'en'],
            ],
        ];

        $riders = collect($ridersData)->map(function (array $riderData) use ($now) {
            $user = User::updateOrCreate(
                ['email' => $riderData['email']],
                [
                    'national_id' => $riderData['national_id'],
                    'first_name' => $riderData['first_name'],
                    'last_name' => $riderData['last_name'],
                    'father_name' => $riderData['father_name'],
                    'phone' => $riderData['phone'],
                    'date_of_birth' => $riderData['date_of_birth'],
                    'role' => UserRole::Rider,
                    'password' => Hash::make($demoPassword),
                    'fyda_verified_at' => $now,
                    'metadata' => $riderData['metadata'] ?? [],
                ]
            );

            FydaVerification::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'national_id' => $user->national_id,
                    'status' => 'verified',
                    'response_payload' => [
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                    ],
                    'verified_at' => $now,
                ]
            );

            return $user;
        });

        $stationsData = [
            [
                'name' => 'Meskel Square Hub',
                'code' => 'MSK01',
                'description' => 'Central hub beside Meskel Square and the light rail.',
                'latitude' => 9.010793,
                'longitude' => 38.761252,
                'capacity' => 24,
                'available_docks' => 8,
                'metadata' => ['landmark' => 'Meskel Square'],
            ],
            [
                'name' => 'Bole Medhanialem',
                'code' => 'BOL02',
                'description' => 'Popular pickup near the airport road and Edna Mall.',
                'latitude' => 8.993209,
                'longitude' => 38.788387,
                'capacity' => 20,
                'available_docks' => 5,
                'metadata' => ['landmark' => 'Edna Mall'],
            ],
            [
                'name' => 'CMC Park',
                'code' => 'CMC03',
                'description' => 'Residential stop serving the CMC and Ayat areas.',
                'latitude' => 9.040541,
                'longitude' => 38.824808,
                'capacity' => 18,
                'available_docks' => 6,
                'metadata' => ['landmark' => 'CMC Park'],
            ],
            [
                'name' => 'Bole Megenagna',
                'code' => 'BMG04',
                'description' => 'Approximate location near Bole Megenagna junction.',
                'latitude' => 9.0052,
                'longitude' => 38.7885,
                'capacity' => 22,
                'available_docks' => 7,
                'metadata' => ['landmark' => 'Bole Megenagna'],
            ],
            [
                'name' => 'Koye Fetche',
                'code' => 'KFY05',
                'description' => 'Koye Fetche neighborhood bike hub.',
                'latitude' => 8.911243,
                'longitude' => 38.817984,
                'capacity' => 16,
                'available_docks' => 5,
                'metadata' => ['landmark' => 'Koye Fetche'],
            ],
        ];

        $stations = collect($stationsData)->mapWithKeys(function (array $stationData) {
            $station = Station::updateOrCreate(
                ['code' => $stationData['code']],
                [
                    'name' => $stationData['name'],
                    'description' => $stationData['description'],
                    'latitude' => $stationData['latitude'],
                    'longitude' => $stationData['longitude'],
                    'capacity' => $stationData['capacity'],
                    'available_docks' => $stationData['available_docks'],
                    'status' => 'active',
                    'metadata' => $stationData['metadata'] ?? [],
                ]
            );

            return [$stationData['code'] => $station];
        });

        $bikesData = [
            [
                'code' => 'BK-ADD-001',
                'station_code' => 'MSK01',
                'model' => 'Swift City 3S',
                'type' => 'electric',
                'battery_level' => 87.4,
                'odometer_km' => 1240.5,
                'price_per_minute' => 3.50,
                'unlock_fee' => 12.00,
                'last_service_days' => 5,
                'controller' => 'CTRL-MSK-001',
                'color' => 'sky blue',
                'last_latitude' => 9.0109,
                'last_longitude' => 38.7619,
                'location_accuracy' => 6.2,
                'location_updated_minutes' => 18,
            ],
            [
                'code' => 'BK-ADD-002',
                'station_code' => 'MSK01',
                'model' => 'Dash Urban',
                'type' => 'standard',
                'battery_level' => null,
                'odometer_km' => 980.2,
                'price_per_minute' => 2.50,
                'unlock_fee' => 8.00,
                'last_service_days' => 12,
                'controller' => 'CTRL-MSK-002',
                'color' => 'graphite',
                'last_latitude' => 9.0115,
                'last_longitude' => 38.7607,
                'location_accuracy' => 5.4,
                'location_updated_minutes' => 26,
            ],
            [
                'code' => 'BK-ADD-003',
                'station_code' => 'MSK01',
                'model' => 'Metro Glide',
                'type' => 'electric',
                'battery_level' => 73.2,
                'odometer_km' => 512.0,
                'price_per_minute' => 3.20,
                'unlock_fee' => 10.00,
                'last_service_days' => 8,
                'controller' => 'CTRL-MSK-003',
                'color' => 'mint',
                'last_latitude' => 9.0099,
                'last_longitude' => 38.7623,
                'location_accuracy' => 4.8,
                'location_updated_minutes' => 11,
            ],
            [
                'code' => 'BK-BOL-004',
                'station_code' => 'BOL02',
                'model' => 'Dash City',
                'type' => 'electric',
                'battery_level' => 65.3,
                'odometer_km' => 2204.7,
                'price_per_minute' => 3.10,
                'unlock_fee' => 10.50,
                'last_service_days' => 6,
                'controller' => 'CTRL-BOL-001',
                'color' => 'charcoal',
                'last_latitude' => 8.9937,
                'last_longitude' => 38.7891,
                'location_accuracy' => 7.0,
                'location_updated_minutes' => 22,
            ],
            [
                'code' => 'BK-BOL-005',
                'station_code' => 'BOL02',
                'model' => 'Bajaj Swift',
                'type' => 'standard',
                'battery_level' => null,
                'odometer_km' => 1433.9,
                'price_per_minute' => 2.10,
                'unlock_fee' => 7.00,
                'last_service_days' => 14,
                'controller' => 'CTRL-BOL-002',
                'color' => 'orange',
                'last_latitude' => 8.9941,
                'last_longitude' => 38.7878,
                'location_accuracy' => 5.9,
                'location_updated_minutes' => 34,
            ],
            [
                'code' => 'BK-BOL-006',
                'station_code' => 'BOL02',
                'model' => 'Urban Ride S',
                'type' => 'electric',
                'battery_level' => 92.7,
                'odometer_km' => 318.6,
                'price_per_minute' => 3.70,
                'unlock_fee' => 11.00,
                'last_service_days' => 4,
                'controller' => 'CTRL-BOL-003',
                'color' => 'yellow',
                'last_latitude' => 8.9928,
                'last_longitude' => 38.7880,
                'location_accuracy' => 4.2,
                'location_updated_minutes' => 9,
            ],
            [
                'code' => 'BK-CMC-007',
                'station_code' => 'CMC03',
                'model' => 'Urban Rider',
                'type' => 'standard',
                'battery_level' => null,
                'odometer_km' => 752.4,
                'price_per_minute' => 2.00,
                'unlock_fee' => 6.00,
                'last_service_days' => 10,
                'controller' => 'CTRL-CMC-001',
                'color' => 'navy',
                'last_latitude' => 9.0409,
                'last_longitude' => 38.8251,
                'location_accuracy' => 5.6,
                'location_updated_minutes' => 27,
            ],
            [
                'code' => 'BK-CMC-008',
                'station_code' => 'CMC03',
                'model' => 'Swift City',
                'type' => 'electric',
                'battery_level' => 58.4,
                'odometer_km' => 1854.1,
                'price_per_minute' => 3.00,
                'unlock_fee' => 9.00,
                'last_service_days' => 9,
                'controller' => 'CTRL-CMC-002',
                'color' => 'teal',
                'last_latitude' => 9.0413,
                'last_longitude' => 38.8245,
                'location_accuracy' => 6.8,
                'location_updated_minutes' => 19,
            ],
            [
                'code' => 'BK-CMC-009',
                'station_code' => 'CMC03',
                'model' => 'Dash City',
                'type' => 'electric',
                'battery_level' => 81.9,
                'odometer_km' => 964.0,
                'price_per_minute' => 3.40,
                'unlock_fee' => 10.00,
                'last_service_days' => 7,
                'controller' => 'CTRL-CMC-003',
                'color' => 'white',
                'last_latitude' => 9.0398,
                'last_longitude' => 38.8256,
                'location_accuracy' => 5.1,
                'location_updated_minutes' => 13,
            ],
            [
                'code' => 'BK-BMG-010',
                'station_code' => 'BMG04',
                'model' => 'Swift City 3S',
                'type' => 'electric',
                'battery_level' => 68.2,
                'odometer_km' => 1450.7,
                'price_per_minute' => 3.30,
                'unlock_fee' => 10.50,
                'last_service_days' => 6,
                'controller' => 'CTRL-BMG-001',
                'color' => 'lime',
                'last_latitude' => 9.0057,
                'last_longitude' => 38.7889,
                'location_accuracy' => 6.1,
                'location_updated_minutes' => 12,
            ],
            [
                'code' => 'BK-BMG-011',
                'station_code' => 'BMG04',
                'model' => 'Urban Rider',
                'type' => 'standard',
                'battery_level' => null,
                'odometer_km' => 905.4,
                'price_per_minute' => 2.20,
                'unlock_fee' => 7.50,
                'last_service_days' => 11,
                'controller' => 'CTRL-BMG-002',
                'color' => 'silver',
                'last_latitude' => 9.0048,
                'last_longitude' => 38.7880,
                'location_accuracy' => 5.2,
                'location_updated_minutes' => 21,
            ],
            [
                'code' => 'BK-KFY-012',
                'station_code' => 'KFY05',
                'model' => 'Dash City',
                'type' => 'electric',
                'battery_level' => 79.5,
                'odometer_km' => 620.1,
                'price_per_minute' => 3.10,
                'unlock_fee' => 9.50,
                'last_service_days' => 8,
                'controller' => 'CTRL-KFY-001',
                'color' => 'turquoise',
                'last_latitude' => 8.911543,
                'last_longitude' => 38.818284,
                'location_accuracy' => 6.4,
                'location_updated_minutes' => 15,
            ],
            [
                'code' => 'BK-KFY-013',
                'station_code' => 'KFY05',
                'model' => 'Swift City',
                'type' => 'standard',
                'battery_level' => null,
                'odometer_km' => 1120.9,
                'price_per_minute' => 2.05,
                'unlock_fee' => 6.50,
                'last_service_days' => 10,
                'controller' => 'CTRL-KFY-002',
                'color' => 'olive',
                'last_latitude' => 8.910943,
                'last_longitude' => 38.817684,
                'location_accuracy' => 5.8,
                'location_updated_minutes' => 24,
            ],
            [
                'code' => 'BK-KFY-014',
                'station_code' => 'KFY05',
                'model' => 'Urban Rider',
                'type' => 'standard',
                'battery_level' => null,
                'odometer_km' => 830.3,
                'price_per_minute' => 2.15,
                'unlock_fee' => 6.75,
                'last_service_days' => 9,
                'controller' => 'CTRL-KFY-003',
                'color' => 'sand',
                'last_latitude' => 8.909129,
                'last_longitude' => 38.818847,
                'location_accuracy' => 5.4,
                'location_updated_minutes' => 16,
            ],
            [
                'code' => 'BK-KFY-015',
                'station_code' => 'KFY05',
                'model' => 'Dash City',
                'type' => 'electric',
                'battery_level' => 74.1,
                'odometer_km' => 540.6,
                'price_per_minute' => 3.05,
                'unlock_fee' => 9.25,
                'last_service_days' => 7,
                'controller' => 'CTRL-KFY-004',
                'color' => 'coral',
                'last_latitude' => 8.908729,
                'last_longitude' => 38.818447,
                'location_accuracy' => 6.0,
                'location_updated_minutes' => 14,
            ],
            [
                'code' => 'BK-KFY-016',
                'station_code' => 'KFY05',
                'model' => 'Swift City 3S',
                'type' => 'electric',
                'battery_level' => 88.6,
                'odometer_km' => 310.8,
                'price_per_minute' => 3.35,
                'unlock_fee' => 10.25,
                'last_service_days' => 5,
                'controller' => 'CTRL-KFY-005',
                'color' => 'aqua',
                'last_latitude' => 8.909329,
                'last_longitude' => 38.818947,
                'location_accuracy' => 4.9,
                'location_updated_minutes' => 10,
            ],
            [
                'code' => 'BK-KFY-017',
                'station_code' => 'KFY05',
                'model' => 'Metro Glide',
                'type' => 'standard',
                'battery_level' => null,
                'odometer_km' => 1012.2,
                'price_per_minute' => 2.05,
                'unlock_fee' => 6.25,
                'last_service_days' => 12,
                'controller' => 'CTRL-KFY-006',
                'color' => 'slate',
                'last_latitude' => 8.908829,
                'last_longitude' => 38.818747,
                'location_accuracy' => 5.7,
                'location_updated_minutes' => 19,
            ],
        ];

        collect($bikesData)->each(function (array $bikeData) use ($stations, $now) {
            /** @var Station $station */
            $station = $stations[$bikeData['station_code']];

            Bike::updateOrCreate(
                ['code' => $bikeData['code']],
                [
                    'station_id' => $station->id,
                    'model' => $bikeData['model'],
                    'type' => $bikeData['type'],
                    'status' => BikeStatus::Available,
                    'is_active' => true,
                    'battery_level' => $bikeData['battery_level'],
                    'odometer_km' => $bikeData['odometer_km'],
                    'price_per_minute' => config('pricing.rides.per_minute_rate', 0.5),
                    'unlock_fee' => config('pricing.rides.unlock_fee', 5),
                    'last_service_at' => $now->copy()->subDays($bikeData['last_service_days']),
                    'hardware' => ['controller' => $bikeData['controller']],
                    'metadata' => ['color' => $bikeData['color']],
                    'last_latitude' => $bikeData['last_latitude'] ?? $station->latitude,
                    'last_longitude' => $bikeData['last_longitude'] ?? $station->longitude,
                    'location_accuracy' => $bikeData['location_accuracy'] ?? null,
                    'location_updated_at' => $now->copy()->subMinutes($bikeData['location_updated_minutes'] ?? 0),
                ]
            );
        });
    }
}
