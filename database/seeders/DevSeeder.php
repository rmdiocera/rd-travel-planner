<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Place;
use App\Models\User;
use Illuminate\Database\Seeder;

class DevSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        Place::factory()->createMany([
            [
                'id' => '01km5bcgx8n4d2pm0xzf12cyjd',
                'name' => 'Nagashima Spa Land',
                'details' => 'Nagashima Spa Land is an amusement park and vacation resort in Kuwana, Mie, Japan, about 30 km west of Nagoya. It opened in 1966, and features an amusement park with several roller coasters, thrill rides, kiddie rides, a water park, a hot springs complex, an outdoor outlet mall, and 3 official hotels.',
                'address' => '333 Nagashimacho Urayasu, Kuwana, Mie 511-1192, Japan',
                'country' => 'Japan',
                'city' => 'Nagoya',
                'website' => 'https://www.nagashima-onsen.co.jp/index.html',
            ],
            [
                'id' => '01km5bcgx8n4d2pm0xzf12cyje',
                'name' => 'Osaka Castle',
                'details' => 'Osaka Castle is a historic castle located in Chūō-ku, Osaka, Japan. It was originally built in the late 16th century by Toyotomi Hideyoshi and has since been reconstructed several times. The castle is known for its impressive architecture and beautiful surrounding park.',
                'address' => '1-1 Osakajo, Chuo Ward, Osaka City, Osaka Prefecture 540-0002, Japan',
                'country' => 'Japan',
                'city' => 'Osaka',
                'website' => 'https://www.osakacastle.net/',
            ],
            [
                'id' => '01km5bcgx8n4d2pm0xzf12cyjf',
                'name' => 'Fushimi Inari Taisha',
                'details' => 'Fushimi Inari Taisha is the head shrine of the god Inari, located in Fushimi Ward, Kyoto, Japan. The shrine is famous for its thousands of vermilion torii gates that trail up the mountain behind the main shrine buildings.',
                'address' => '68 Fukakusa Yabunouchicho, Fushimi Ward, Kyoto, 612-0882, Japan',
                'country' => 'Japan',
                'city' => 'Kyoto',
                'website' => 'https://inari.jp/',
            ],
            [
                'id' => '01km5bcgx8n4d2pm0xzf12cyjg',
                'name' => 'Kinkaku-ji (Golden Pavilion)',
                'details' => 'Kinkaku-ji is a Zen Buddhist temple in Kyoto, Japan. The top two floors of the pavilion are completely covered in gold leaf, and the temple overlooks a large reflective pond. It is one of Japan\'s most visited buildings.',
                'address' => '1 Kinkakujicho, Kita Ward, Kyoto, 603-8361, Japan',
                'country' => 'Japan',
                'city' => 'Kyoto',
                'website' => 'https://www.shokoku-ji.jp/kinkakuji/',
            ],
            [
                'id' => '01km5bcgx8n4d2pm0xzf12cyjh',
                'name' => 'Kobe Harborland',
                'details' => 'Kobe Harborland is a large waterfront shopping and entertainment complex located along the port of Kobe. It features shopping malls, restaurants, a Ferris wheel, and scenic views of Osaka Bay, making it a popular leisure destination.',
                'address' => '1 Higashikawasaki-cho, Chuo Ward, Kobe, Hyogo 650-0044, Japan',
                'country' => 'Japan',
                'city' => 'Kobe',
                'website' => 'https://kobe-harborland.co.jp/',
            ],
        ]);

        $user->itineraries()->create([
            'name' => 'Japan Trip',
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-10',
            'notes' => 'Trip to Kyoto, Kobe, Osaka, and Nagoya.',
        ])->spots()->createMany([
            [
                'place_id' => '01km5bcgx8n4d2pm0xzf12cyjd',
                'visit_date' => '2026-05-02',
            ],
            [
                'place_id' => '01km5bcgx8n4d2pm0xzf12cyjg',
                'visit_date' => '2026-05-03',
            ],
        ]);
    }
}
