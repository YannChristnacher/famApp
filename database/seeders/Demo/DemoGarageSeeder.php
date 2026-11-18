<?php

namespace Database\Seeders\Demo;

use App\Models\Garage\Tool;
use App\Models\Garage\ToolShelf;
use App\Models\User;
use App\Services\Garage\BrandService;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;

/**
 * Tool shelves of every demo member and twenty real tools: shared brands
 * (resolved like a typed brand, so "bosch" joins "Bosch"), accessories, zero
 * to three photos drawn locally, shelves, some comments.
 */
class DemoGarageSeeder extends Seeder
{
    /**
     * Tool shelves of each member, in their order.
     *
     * @var array<string, list<string>>
     */
    private const SHELVES = [
        DemoMembers::ADMIN_EMAIL => ['Établi', 'Armoire métallique'],
        DemoMembers::JULIEN_EMAIL => ['Mur du fond', 'Cabanon', 'Étagère haute'],
        DemoMembers::SOPHIE_EMAIL => ['Placard de l\'entrée', 'Abri de jardin'],
        DemoMembers::THOMAS_EMAIL => ['Caisse à outils'],
    ];

    /**
     * Tools of each member: name, brand, shelves, number of photos,
     * accessories, comment.
     *
     * @var array<string, list<array{0: string, 1: string|null, 2: list<string>, 3: int<0, 3>, 4: list<string>, 5?: string}>>
     */
    private const TOOLS = [
        DemoMembers::ADMIN_EMAIL => [
            ['Perceuse visseuse sans fil 18 V', 'Makita', ['Établi'], 3, ['2 batteries 18 V 3 Ah', 'Chargeur rapide', 'Coffret de 30 embouts'], 'Les deux batteries tiennent une bonne demi-journée.'],
            ['Scie sauteuse', 'Bosch', ['Établi'], 2, ['Lames bois', 'Lames métal', 'Pare-éclats']],
            ['Ponceuse excentrique 150 mm', 'Festool', ['Armoire métallique'], 1, ['Abrasifs grain 80', 'Abrasifs grain 120', 'Abrasifs grain 240']],
            ['Niveau à bulle 60 cm', 'Stanley', ['Armoire métallique'], 0, []],
            ['Coffret de tournevis', 'Facom', ['Établi', 'Armoire métallique'], 1, [], 'Il manque le cruciforme PH2, je le cherche encore.'],
            ['Mètre ruban 5 m', 'Stanley', [], 0, []],
        ],
        DemoMembers::JULIEN_EMAIL => [
            ['Perforateur SDS-Plus', 'DeWalt', ['Mur du fond'], 2, ['Forets béton 6, 8 et 10 mm', 'Burin plat', 'Poignée latérale']],
            ['Scie circulaire 190 mm', 'Makita', ['Mur du fond'], 1, ['Lame 24 dents', 'Guide parallèle']],
            ['Meuleuse d\'angle 125 mm', 'BOSCH', ['Cabanon'], 1, ['Disques à tronçonner', 'Disque diamant'], 'Toujours porter les lunettes, le carter est un peu lâche.'],
            ['Serre-joints (lot de 4)', 'Bessey', ['Étagère haute'], 0, []],
            ['Escabeau 5 marches', 'Hailo', [], 1, []],
            ['Visseuse à chocs 18 V', 'Ryobi', ['Mur du fond', 'Étagère haute'], 3, ['Batterie 18 V 2 Ah', 'Chargeur']],
        ],
        DemoMembers::SOPHIE_EMAIL => [
            ['Nettoyeur haute pression', 'Kärcher', ['Abri de jardin'], 2, ['Lance rotative', 'Brosse de lavage', 'Tuyau 10 m']],
            ['Taille-haie électrique', 'bosch', ['Abri de jardin'], 1, []],
            ['Tondeuse à gazon sans fil', 'Ryobi', ['Abri de jardin'], 1, ['Bac de ramassage', 'Kit mulching'], 'Prévenir avant de l\'emprunter, je tonds le samedi matin.'],
            ['Échelle télescopique 3,8 m', null, ['Placard de l\'entrée'], 0, []],
        ],
        DemoMembers::THOMAS_EMAIL => [
            ['Fer à souder 80 W', 'Weller', ['Caisse à outils'], 1, ['Panne fine', 'Support et éponge', 'Bobine d\'étain']],
            ['Multimètre numérique', null, ['Caisse à outils'], 0, ['Cordons de test']],
            ['Pistolet à colle chaude', 'Bosch', [], 2, ['Bâtons de colle 11 mm']],
            ['Scie à onglet', 'DeWalt', ['Caisse à outils'], 0, []],
        ],
    ];

    public function __construct(
        private readonly DemoImageGenerator $images,
        private readonly BrandService $brands,
    ) {}

    public function run(): void
    {
        $daysAgo = 35;

        foreach (self::SHELVES as $email => $names) {
            $owner = User::query()->where('email', $email)->sole();
            $shelves = $this->shelves($owner, $names);

            foreach (self::TOOLS[$email] as $tool) {
                $this->tool($owner, $tool, $shelves, now()->subDays($daysAgo)->subMinutes(53 * $daysAgo));
                $daysAgo = max(0, $daysAgo - 2);
            }
        }
    }

    /**
     * @param  list<string>  $names
     * @return array<string, ToolShelf> by name
     */
    private function shelves(User $owner, array $names): array
    {
        $shelves = [];
        foreach ($names as $name) {
            $shelves[$name] = ToolShelf::factory()->for($owner)->create(['name' => $name]);
        }

        return $shelves;
    }

    /**
     * @param  array{0: string, 1: string|null, 2: list<string>, 3: int<0, 3>, 4: list<string>, 5?: string}  $data
     * @param  array<string, ToolShelf>  $shelves
     */
    private function tool(User $owner, array $data, array $shelves, CarbonInterface $addedAt): void
    {
        [$name, $brandName, $shelfNames, $photoCount, $accessories] = $data;
        $brand = $this->brands->resolve($brandName);

        $tool = Tool::factory()->for($owner)->create([
            'name' => $name,
            'brand_id' => $brand?->id,
            'comment' => $data[5] ?? null,
            'created_at' => $addedAt,
            'updated_at' => $addedAt,
        ]);

        $tool->shelves()->attach(array_map(fn (string $shelfName) => $shelves[$shelfName]->id, $shelfNames));

        for ($position = 1; $position <= $photoCount; $position++) {
            $tool->photos()->create([
                'path' => $this->images->toolPhoto($name, $brand?->name, $position),
                'position' => $position,
            ]);
        }

        foreach ($accessories as $index => $accessory) {
            $tool->accessories()->create(['name' => $accessory, 'position' => $index + 1]);
        }
    }
}
