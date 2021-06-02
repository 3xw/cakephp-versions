<?php
declare(strict_types=1);

namespace Trois\Versions\Test\TestCase\Model\Behavior;

use Cake\ORM\Table;
use Cake\TestSuite\TestCase;
use Trois\Versions\Model\Behavior\VersionsBehavior;

/**
 * Trois\Versions\Model\Behavior\VersionsBehavior Test Case
 */
class VersionsBehaviorTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \Trois\Versions\Model\Behavior\VersionsBehavior
     */
    protected $Versions;

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $table = new Table();
        $this->Versions = new VersionsBehavior($table);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown(): void
    {
        unset($this->Versions);

        parent::tearDown();
    }
}
