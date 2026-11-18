<?php

namespace Database\Seeders\Demo;

use App\Models\Library\Book;
use App\Models\Library\Bookshelf;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;

/**
 * Bookshelves of every demo member and thirty real books (French editions,
 * real ISBN-13), on zero to several bookshelves, some with a comment, most
 * with a cover drawn locally.
 */
class DemoLibrarySeeder extends Seeder
{
    /**
     * Bookshelves of each member, in their order.
     *
     * @var array<string, list<string>>
     */
    private const BOOKSHELVES = [
        DemoMembers::ADMIN_EMAIL => ['Salon', 'Bureau', 'Romans policiers'],
        DemoMembers::JULIEN_EMAIL => ['Bandes dessinées', 'Classiques', 'Chambre'],
        DemoMembers::SOPHIE_EMAIL => ['Salon', 'À lire', 'Étagère du couloir'],
        DemoMembers::THOMAS_EMAIL => ['Science-fiction', 'Chevet'],
    ];

    /**
     * Books of each member: title, author, ISBN-13, bookshelves, cover drawn,
     * comment.
     *
     * @var array<string, list<array{0: string, 1: string, 2: string, 3: list<string>, 4: bool, 5?: string}>>
     */
    private const BOOKS = [
        DemoMembers::ADMIN_EMAIL => [
            ['Le Petit Prince', 'Antoine de Saint-Exupéry', '9782070612758', ['Salon'], true, 'Offert par mamie pour mes huit ans, la dédicace est sur la première page.'],
            ['Madame Bovary', 'Gustave Flaubert', '9782070413119', ['Bureau'], true],
            ['Germinal', 'Émile Zola', '9782253004226', ['Bureau'], true],
            ['Les Misérables (tome 1)', 'Victor Hugo', '9782253096337', ['Salon', 'Bureau'], true, 'Le tome 2 est chez Julien.'],
            ['L\'Élégance du hérisson', 'Muriel Barbery', '9782070391653', ['Salon'], true],
            ['La Vérité sur l\'affaire Harry Quebert', 'Joël Dicker', '9782877068161', ['Romans policiers'], true, 'Impossible à lâcher, à prêter sans hésiter.'],
            ['Le Nom de la rose', 'Umberto Eco', '9782253033134', ['Romans policiers', 'Salon'], false],
            ['Le Parfum', 'Patrick Süskind', '9782253044901', [], true],
            ['Sapiens : Une brève histoire de l\'humanité', 'Yuval Noah Harari', '9782226257017', ['Bureau'], true],
        ],
        DemoMembers::JULIEN_EMAIL => [
            ['L\'Étranger', 'Albert Camus', '9782070360024', ['Classiques'], true],
            ['La Peste', 'Albert Camus', '9782070360420', ['Classiques'], true],
            ['1984', 'George Orwell', '9782070368228', ['Classiques', 'Chambre'], true, 'Annoté au crayon pendant le lycée.'],
            ['Le Meilleur des mondes', 'Aldous Huxley', '9782266283038', ['Chambre'], false],
            ['Astérix le Gaulois', 'René Goscinny, Albert Uderzo', '9782012101333', ['Bandes dessinées'], true],
            ['Le Lotus bleu', 'Hergé', '9782203001046', ['Bandes dessinées'], true],
            ['Objectif Lune', 'Hergé', '9782203001169', [], true],
        ],
        DemoMembers::SOPHIE_EMAIL => [
            ['L\'Étranger', 'Albert Camus', '9782070360024', ['Salon'], true],
            ['Harry Potter à l\'école des sorciers', 'J.K. Rowling', '9782070584628', ['Étagère du couloir'], true],
            ['L\'Écume des jours', 'Boris Vian', '9782253140870', ['Salon'], true],
            ['Les Fleurs du mal', 'Charles Baudelaire', '9782253007104', ['Salon', 'Étagère du couloir'], false],
            ['L\'Alchimiste', 'Paulo Coelho', '9782290004449', ['À lire'], true],
            ['Da Vinci Code', 'Dan Brown', '9782253127079', [], true, 'Couverture un peu abîmée, il a pris l\'eau à la plage.'],
            ['Vingt mille lieues sous les mers', 'Jules Verne', '9782253006329', ['Étagère du couloir'], true],
            ['Le Tour du monde en quatre-vingts jours', 'Jules Verne', '9782253006305', ['À lire'], false],
        ],
        DemoMembers::THOMAS_EMAIL => [
            ['Le Petit Prince', 'Antoine de Saint-Exupéry', '9782070612758', ['Chevet'], true],
            ['Harry Potter à l\'école des sorciers', 'J.K. Rowling', '9782070584628', ['Chevet'], true],
            ['Harry Potter et la chambre des secrets', 'J.K. Rowling', '9782070541270', ['Chevet'], true],
            ['Dune', 'Frank Herbert', '9782266320481', ['Science-fiction'], true, 'Lu deux fois, le meilleur roman de science-fiction.'],
            ['Fondation', 'Isaac Asimov', '9782070360536', ['Science-fiction'], false],
            ['Le Hobbit', 'J.R.R. Tolkien', '9782253049418', ['Science-fiction', 'Chevet'], true],
        ],
    ];

    public function __construct(private readonly DemoImageGenerator $images) {}

    public function run(): void
    {
        $daysAgo = 40;

        foreach (self::BOOKSHELVES as $email => $names) {
            $owner = User::query()->where('email', $email)->sole();
            $bookshelves = $this->bookshelves($owner, $names);

            foreach (self::BOOKS[$email] as $book) {
                $this->book($owner, $book, $bookshelves, now()->subDays($daysAgo)->subMinutes(37 * $daysAgo));
                $daysAgo = max(0, $daysAgo - 1);
            }
        }
    }

    /**
     * @param  list<string>  $names
     * @return array<string, Bookshelf> by name
     */
    private function bookshelves(User $owner, array $names): array
    {
        $bookshelves = [];
        foreach ($names as $name) {
            $bookshelves[$name] = Bookshelf::factory()->for($owner)->create(['name' => $name]);
        }

        return $bookshelves;
    }

    /**
     * @param  array{0: string, 1: string, 2: string, 3: list<string>, 4: bool, 5?: string}  $data
     * @param  array<string, Bookshelf>  $bookshelves
     */
    private function book(User $owner, array $data, array $bookshelves, CarbonInterface $addedAt): void
    {
        [$title, $author, $isbn, $shelfNames, $withCover] = $data;

        $book = Book::factory()->for($owner)->create([
            'title' => $title,
            'author' => $author,
            'isbn' => $isbn,
            'comment' => $data[5] ?? null,
            'cover_path' => $withCover ? $this->images->cover($title, $author) : null,
            'created_at' => $addedAt,
            'updated_at' => $addedAt,
        ]);

        $book->bookshelves()->attach(array_map(fn (string $name) => $bookshelves[$name]->id, $shelfNames));
    }
}
