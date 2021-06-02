<?php
declare(strict_types=1);

namespace Trois\Versions\Test\TestCase\Model\Table;

use Cake\TestSuite\TestCase;
use Trois\Versions\Model\Table\VersionsTable;

/**
 * Trois\Versions\Model\Table\VersionsTable Test Case
 */
class VersionsTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \Trois\Versions\Model\Table\VersionsTable
     */
    protected $Versions;

    /**
     * Fixtures
     *
     * @var array
     */
    protected $fixtures = [
        'plugin.Trois/Versions.Versions',
        'plugin.Trois/Versions.Users',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('Versions') ? [] : ['className' => VersionsTable::class];
        $this->Versions = $this->getTableLocator()->get('Versions', $config);
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

    /**
     * Test validationDefault method
     *
     * @return void
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test buildRules method
     *
     * @return void
     */
    public function testBuildRules(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
