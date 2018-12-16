<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Cycle\Tests;

use Spiral\Cycle\Heap\Heap;
use Spiral\Cycle\Heap\Node;
use Spiral\Cycle\Mapper\Mapper;
use Spiral\Cycle\Relation;
use Spiral\Cycle\Schema;
use Spiral\Cycle\Select;
use Spiral\Cycle\Select\JoinableLoader;
use Spiral\Cycle\Tests\Fixtures\Nested;
use Spiral\Cycle\Tests\Fixtures\Profile;
use Spiral\Cycle\Tests\Fixtures\User;
use Spiral\Cycle\Tests\Traits\TableTrait;
use Spiral\Cycle\Transaction;
use Spiral\Database\Injection\Parameter;

abstract class BelongsToRelationTest extends BaseTest
{
    use TableTrait;

    public function setUp()
    {
        parent::setUp();

        $this->makeTable('user', [
            'id'      => 'primary',
            'email'   => 'string',
            'balance' => 'float'
        ]);

        $this->getDatabase()->table('user')->insertMultiple(
            ['email', 'balance'],
            [
                ['hello@world.com', 100],
                ['another@world.com', 200],
            ]
        );

        $this->makeTable('profile', [
            'id'      => 'primary',
            'user_id' => 'integer',
            'image'   => 'string'
        ]);

        $this->getDatabase()->table('profile')->insertMultiple(
            ['user_id', 'image'],
            [
                [1, 'image.png'],
                [2, 'second.png'],
                [2, 'third.png'],
            ]
        );

        $this->makeTable('nested', [
            'id'         => 'primary',
            'profile_id' => 'integer',
            'label'      => 'string'
        ]);

        $this->getDatabase()->table('nested')->insertMultiple(
            ['profile_id', 'label'],
            [
                [1, 'nested-label'],
            ]
        );

        $this->makeFK('profile', 'user_id', 'user', 'id');
        $this->makeFK('nested', 'profile_id', 'profile', 'id');

        $this->orm = $this->withSchema(new Schema([
            User::class    => [
                Schema::ALIAS       => 'user',
                Schema::MAPPER      => Mapper::class,
                Schema::DATABASE    => 'default',
                Schema::TABLE       => 'user',
                Schema::PRIMARY_KEY => 'id',
                Schema::COLUMNS     => ['id', 'email', 'balance'],
                Schema::SCHEMA      => [],
                Schema::RELATIONS   => []
            ],
            Profile::class => [
                Schema::ALIAS       => 'profile',
                Schema::MAPPER      => Mapper::class,
                Schema::DATABASE    => 'default',
                Schema::TABLE       => 'profile',
                Schema::PRIMARY_KEY => 'id',
                Schema::COLUMNS     => ['id', 'user_id', 'image'],
                Schema::SCHEMA      => [],
                Schema::RELATIONS   => [
                    'user' => [
                        Relation::TYPE   => Relation::BELONGS_TO,
                        Relation::TARGET => User::class,
                        Relation::SCHEMA => [
                            Relation::CASCADE   => true,
                            Relation::INNER_KEY => 'user_id',
                            Relation::OUTER_KEY => 'id',
                        ],
                    ]
                ]
            ],
            Nested::class  => [
                Schema::ALIAS       => 'nested',
                Schema::MAPPER      => Mapper::class,
                Schema::DATABASE    => 'default',
                Schema::TABLE       => 'nested',
                Schema::PRIMARY_KEY => 'id',
                Schema::COLUMNS     => ['id', 'profile_id', 'label'],
                Schema::SCHEMA      => [],
                Schema::RELATIONS   => [
                    'profile' => [
                        Relation::TYPE   => Relation::BELONGS_TO,
                        Relation::TARGET => Profile::class,
                        Relation::SCHEMA => [
                            Relation::CASCADE   => true,
                            Relation::INNER_KEY => 'profile_id',
                            Relation::OUTER_KEY => 'id',
                        ],
                    ]
                ]
            ]
        ]));
    }

    public function testFetchRelation()
    {
        $selector = new Select($this->orm, Profile::class);
        $selector->load('user')->orderBy('profile.id');

        $this->assertEquals([
            [
                'id'      => 1,
                'user_id' => 1,
                'image'   => 'image.png',
                'user'    => [
                    'id'      => 1,
                    'email'   => 'hello@world.com',
                    'balance' => 100.0,
                ],
            ],
            [
                'id'      => 2,
                'user_id' => 2,
                'image'   => 'second.png',
                'user'    => [
                    'id'      => 2,
                    'email'   => 'another@world.com',
                    'balance' => 200.0,
                ],
            ],
            [
                'id'      => 3,
                'user_id' => 2,
                'image'   => 'third.png',
                'user'    => [
                    'id'      => 2,
                    'email'   => 'another@world.com',
                    'balance' => 200.0,
                ],
            ],
        ], $selector->fetchData());
    }

    public function testFetchRelationInload()
    {
        $selector = new Select($this->orm, Profile::class);
        $selector->load('user', ['method' => JoinableLoader::INLOAD])
            ->orderBy('profile.id');

        $this->assertEquals([
            [
                'id'      => 1,
                'user_id' => 1,
                'image'   => 'image.png',
                'user'    => [
                    'id'      => 1,
                    'email'   => 'hello@world.com',
                    'balance' => 100.0,
                ],
            ],
            [
                'id'      => 2,
                'user_id' => 2,
                'image'   => 'second.png',
                'user'    => [
                    'id'      => 2,
                    'email'   => 'another@world.com',
                    'balance' => 200.0,
                ],
            ],
            [
                'id'      => 3,
                'user_id' => 2,
                'image'   => 'third.png',
                'user'    => [
                    'id'      => 2,
                    'email'   => 'another@world.com',
                    'balance' => 200.0,
                ],
            ],
        ], $selector->fetchData());
    }

    public function testAccessEntities()
    {
        $selector = new Select($this->orm, Profile::class);
        $selector->load('user')->orderBy('profile.id');
        $result = $selector->fetchAll();

        $this->assertInstanceOf(Profile::class, $result[0]);
        $this->assertInstanceOf(User::class, $result[0]->user);
        $this->assertEquals('hello@world.com', $result[0]->user->email);

        $this->assertInstanceOf(Profile::class, $result[1]);
        $this->assertInstanceOf(User::class, $result[1]->user);
        $this->assertEquals('another@world.com', $result[1]->user->email);

        $this->assertInstanceOf(Profile::class, $result[2]);
        $this->assertInstanceOf(User::class, $result[2]->user);
        $this->assertEquals('another@world.com', $result[2]->user->email);

        $this->assertSame($result[1]->user, $result[2]->user);
    }

    public function testCreateWithRelations()
    {
        $u = new User();
        $u->email = 'test@email.com';
        $u->balance = 300;

        $p = new Profile();
        $p->image = 'magic.gif';
        $p->user = $u;

        $tr = new Transaction($this->orm);
        $tr->persist($p);
        $tr->run();

        $this->assertEquals(3, $u->id);
        $this->assertEquals(4, $p->id);

        $this->assertTrue($this->orm->getHeap()->has($u));
        $this->assertSame(Node::MANAGED, $this->orm->getHeap()->get($u)->getStatus());

        $this->assertTrue($this->orm->getHeap()->has($p));
        $this->assertSame(Node::MANAGED, $this->orm->getHeap()->get($p)->getStatus());

        $this->assertSame($u->id, $this->orm->getHeap()->get($p)->getData()['user_id']);

        $selector = new Select($this->orm, Profile::class);
        $selector->load('user');

        $this->assertEquals([
            [
                'id'      => 4,
                'user_id' => 3,
                'image'   => 'magic.gif',
                'user'    => [
                    'id'      => 3,
                    'email'   => 'test@email.com',
                    'balance' => 300.0,
                ],
            ]
        ], $selector->wherePK(4)->fetchData());
    }

    public function testNoWriteQueries()
    {
        $u = new User();
        $u->email = 'test@email.com';
        $u->balance = 300;

        $p = new Profile();
        $p->image = 'magic.gif';
        $p->user = $u;

        $tr = new Transaction($this->orm);
        $tr->persist($p);
        $tr->run();

        $this->orm = $this->orm->withHeap(new Heap());
        $selector = new Select($this->orm, Profile::class);
        $p = $selector->load('user')->wherePK(4)->fetchOne();

        $this->captureWriteQueries();
        $tr = new Transaction($this->orm);
        $tr->persist($p);
        $tr->run();
        $this->assertNumWrites(0);
    }

    public function testSetExistedParent()
    {
        $s = new Select($this->orm, User::class);
        $u = $s->wherePK(1)->fetchOne();

        $p = new Profile();
        $p->image = 'magic.gif';
        $p->user = $u;

        $tr = new Transaction($this->orm);
        $tr->persist($p);
        $tr->run();

        $this->assertEquals(1, $u->id);
        $this->assertEquals(4, $p->id);

        $this->assertTrue($this->orm->getHeap()->has($p));
        $this->assertSame(Node::MANAGED, $this->orm->getHeap()->get($p)->getStatus());

        $this->assertSame($u->id, $this->orm->getHeap()->get($p)->getData()['user_id']);

        $selector = new Select($this->orm, Profile::class);
        $selector->load('user');

        $this->assertEquals([
            [
                'id'      => 4,
                'user_id' => 1,
                'image'   => 'magic.gif',
                'user'    => [
                    'id'      => 1,
                    'email'   => 'hello@world.com',
                    'balance' => 100.0,
                ],
            ]
        ], $selector->wherePK(4)->fetchData());
    }

    public function testChangeParent()
    {
        $s = new Select($this->orm, Profile::class);
        $p = $s->wherePK(1)->load('user')->fetchOne();

        $s = new Select($this->orm, User::class);
        $u = $s->wherePK(2)->fetchOne();

        $p->user = $u;

        $tr = new Transaction($this->orm);
        $tr->persist($p);
        $tr->run();

        $this->assertEquals([
            [
                'id'      => 1,
                'user_id' => 2,
                'image'   => 'image.png',
                'user'    => [
                    'id'      => 2,
                    'email'   => 'another@world.com',
                    'balance' => 200.0,
                ],
            ]
        ], (new Select($this->orm, Profile::class))->load('user')->wherePK(
            1
        )->fetchData());
    }

    public function testExchangeParents()
    {
        $s = new Select($this->orm, Profile::class);
        list($a, $b) = $s->wherePK(new Parameter([1, 2]))->orderBy('profile.id')
            ->load('user')->fetchAll();

        list($a->user, $b->user) = [$b->user, $a->user];

        $this->captureWriteQueries();
        $tr = new Transaction($this->orm);
        $tr->persist($a);
        $tr->persist($b);
        $tr->run();
        $this->assertNumWrites(2);

        $this->captureWriteQueries();
        $tr = new Transaction($this->orm);
        $tr->persist($a);
        $tr->persist($b);
        $tr->run();
        $this->assertNumWrites(0);

        $s = new Select($this->orm->withHeap(new Heap()), Profile::class);
        list($a2, $b2) = $s->wherePK(new Parameter([1, 2]))->orderBy('profile.id')
            ->load('user')->fetchAll();

        $this->assertSame($a->user->id, $a2->user->id);
        $this->assertSame($b->user->id, $b2->user->id);
    }

    /**
     * @expectedException \Spiral\Cycle\Exception\Relation\NullException
     */
    public function testSetNullException()
    {
        $s = new Select($this->orm, Profile::class);
        $p = $s->wherePK(1)->load('user')->fetchOne();
        $p->user = null;

        try {
            $tr = new Transaction($this->orm);
            $tr->persist($p);
            $tr->run();
        } catch (\Throwable $e) {
            throw $e;
        } finally {
            // we do not expect state to be consistent as transaction failed, see rollback tests
            $this->orm = $this->orm->withHeap(new Heap());
        }
    }

    public function testSetNull()
    {
        $this->orm = $this->withSchema(new Schema([
            User::class    => [
                Schema::ALIAS       => 'user',
                Schema::MAPPER      => Mapper::class,
                Schema::DATABASE    => 'default',
                Schema::TABLE       => 'user',
                Schema::PRIMARY_KEY => 'id',
                Schema::COLUMNS     => ['id', 'email', 'balance'],
                Schema::SCHEMA      => [],
                Schema::RELATIONS   => []
            ],
            Profile::class => [
                Schema::ALIAS       => 'profile',
                Schema::MAPPER      => Mapper::class,
                Schema::DATABASE    => 'default',
                Schema::TABLE       => 'profile',
                Schema::PRIMARY_KEY => 'id',
                Schema::COLUMNS     => ['id', 'user_id', 'image'],
                Schema::SCHEMA      => [],
                Schema::RELATIONS   => [
                    'user' => [
                        Relation::TYPE   => Relation::BELONGS_TO,
                        Relation::TARGET => User::class,
                        Relation::SCHEMA => [
                            Relation::CASCADE   => true,
                            Relation::INNER_KEY => 'user_id',
                            Relation::OUTER_KEY => 'id',
                            Relation::NULLABLE  => true
                        ],
                    ]
                ]
            ],
            Nested::class  => [
                Schema::ALIAS       => 'nested',
                Schema::MAPPER      => Mapper::class,
                Schema::DATABASE    => 'default',
                Schema::TABLE       => 'nested',
                Schema::PRIMARY_KEY => 'id',
                Schema::COLUMNS     => ['id', 'profile_id', 'label'],
                Schema::SCHEMA      => [],
                Schema::RELATIONS   => [
                    'profile' => [
                        Relation::TYPE   => Relation::BELONGS_TO,
                        Relation::TARGET => Profile::class,
                        Relation::SCHEMA => [
                            Relation::CASCADE   => true,
                            Relation::INNER_KEY => 'profile_id',
                            Relation::OUTER_KEY => 'id',
                        ],
                    ]
                ]
            ]
        ]));

        $this->makeTable('profile', [
            'id'      => 'primary',
            'user_id' => 'integer,nullable',
            'image'   => 'string'
        ]);

        $s = new Select($this->orm, Profile::class);
        $p = $s->wherePK(1)->load('user')->fetchOne();
        $p->user = null;

        $this->captureWriteQueries();
        $tr = new Transaction($this->orm);
        $tr->persist($p);
        $tr->run();
        $this->assertNumWrites(1);

        // consecutive
        $this->captureWriteQueries();
        $tr = new Transaction($this->orm);
        $tr->persist($p);
        $tr->run();
        $this->assertNumWrites(0);

        $s = new Select($this->orm->withHeap(new Heap()), Profile::class);
        $p = $s->wherePK(1)->load('user')->fetchOne();

        $this->assertSame(null, $p->user);
    }

    public function testNested()
    {
        $n = new Nested();
        $n->label = 'nested label';
        $n->profile = new Profile();
        $n->profile->image = 'profile';
        $n->profile->user = new User();
        $n->profile->user->email = "new@email.com";
        $n->profile->user->balance = 999;

        $this->captureWriteQueries();
        $tr = new Transaction($this->orm);
        $tr->persist($n);
        $tr->run();
        $this->assertNumWrites(3);

        $this->captureWriteQueries();
        $tr = new Transaction($this->orm);
        $tr->persist($n);
        $tr->run();
        $this->assertNumWrites(0);

        $s = new Select($this->orm->withHeap(new Heap()), Nested::class);
        $n = $s->wherePK(2)->load('profile.user')->fetchOne();

        $this->assertSame('profile', $n->profile->image);
        $this->assertSame('new@email.com', $n->profile->user->email);
    }
}