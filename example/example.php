<?php
/** @var \Doctrine\ORM\EntityManager $entityManager */
$entityManager = require_once __DIR__ . '/../bootstrap.php';

use Doctrine\ORM\Mapping as ORM;
use Rhumsaa\Uuid\Uuid;
use Tuck\EventDispatcher\BufferedEventDispatcher;
use Tuck\EventDispatcher\SimpleEventDispatcher;

$connection = $entityManager->getConnection();

$eventStore = new Broadway\EventStore\DBALEventStore(
    $connection,
    new \Broadway\Serializer\SimpleInterfaceSerializer(),
    new \Broadway\Serializer\SimpleInterfaceSerializer(),
    'Events'
);

$eventDispatcher = new BufferedEventDispatcher(new SimpleEventDispatcher());
$eventUoW = new \Tuck\DoctrineEventStore\UnitOfWork($eventStore, $eventDispatcher);

$entityManager->getEventManager()->addEventSubscriber(
    new Tuck\DoctrineEventStore\EventCollector($eventUoW)
);


/**
 * @ORM\Entity()
 */
class Book implements \Broadway\Domain\AggregateRoot
{
    use \Tuck\DoctrineEventStore\EntityWithEvents;

    /**
     * @ORM\Id()
     * @ORM\Column(type="string", length=64)
     */
    private $id;

    /**
     * @ORM\Column(type="string")
     */
    private $title;

    private function __construct()
    {
    }

    public static function purchase($title)
    {
        $book = new Book();
        $book->id = (string)Uuid::uuid4();
        $book->title = $title;

        $book->raise(new BookPurchased($book->id, $title));

        return $book;
    }

    public function loan()
    {
        // do stuff
        $this->raise(new BookLoanedOut($this->id, $this->title));
    }

    public function getAggregateRootId()
    {
        return $this->id;
    }
}

abstract class BookEvent implements \Broadway\Serializer\SerializableInterface
{
    private $bookId;
    private $title;

    public function __construct($bookId, $title)
    {
        $this->bookId = $bookId;
        $this->title = $title;
    }

    public function getBookId()
    {
        return $this->bookId;
    }

    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @return mixed The object instance
     */
    public static function deserialize(array $data)
    {
        return new static($data['book_id'], $data['title']);
    }

    /**
     * @return array
     */
    public function serialize()
    {
        return [
            'book_id' => $this->bookId,
            'title' => $this->title
        ];
    }
}

class BookPurchased extends BookEvent
{
}

class BookLoanedOut extends BookEvent
{
}

// Simplified domain part
$commandHandler = function () use ($entityManager) {
    $book = Book::purchase('Moby Dick');
    $book->loan();
    $entityManager->persist($book);
};

// Hang some listeners in there
$eventDispatcher->addListener(
    BookPurchased::class,
    function (BookPurchased $event) {
        echo "Bought new book: " . $event->getTitle() . "\n";
    }
);
$eventDispatcher->addListener(
    BookLoanedOut::class,
    function (BookLoanedOut $event) {
        echo "Loaned out book: " . $event->getTitle() . "\n";
    }
);


// This part would be split into a couple of command bus decorators.
// The important part is that we widen the transaction to include both
// the Doctrine UoW flush and our own UoW flush.
$entityManager->beginTransaction();
try {
    $commandHandler();
    $entityManager->flush();

    $entityManager->commit();
} catch (Exception $e) {
    echo 'ERROR: ' . $e->getMessage() . '. Rolling back!';

    $eventUoW->clear();
    $entityManager->rollBack();

    echo 'Rolled back';
}
$eventDispatcher->dispatchPendingEvents();
