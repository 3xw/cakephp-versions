<?php
declare(strict_types=1);

namespace Trois\Versions\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
* Versions Model
*
* @property \Trois\Versions\Model\Table\VersionsTable&\Cake\ORM\Association\BelongsTo $Versions
* @property \Trois\Versions\Model\Table\UsersTable&\Cake\ORM\Association\BelongsTo $Users
* @property \Trois\Versions\Model\Table\VersionsTable&\Cake\ORM\Association\HasMany $Versions
*
* @method \Trois\Versions\Model\Entity\Version newEmptyEntity()
* @method \Trois\Versions\Model\Entity\Version newEntity(array $data, array $options = [])
* @method \Trois\Versions\Model\Entity\Version[] newEntities(array $data, array $options = [])
* @method \Trois\Versions\Model\Entity\Version get($primaryKey, $options = [])
* @method \Trois\Versions\Model\Entity\Version findOrCreate($search, ?callable $callback = null, $options = [])
* @method \Trois\Versions\Model\Entity\Version patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
* @method \Trois\Versions\Model\Entity\Version[] patchEntities(iterable $entities, array $data, array $options = [])
* @method \Trois\Versions\Model\Entity\Version|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
* @method \Trois\Versions\Model\Entity\Version saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
* @method \Trois\Versions\Model\Entity\Version[]|\Cake\Datasource\ResultSetInterface|false saveMany(iterable $entities, $options = [])
* @method \Trois\Versions\Model\Entity\Version[]|\Cake\Datasource\ResultSetInterface saveManyOrFail(iterable $entities, $options = [])
* @method \Trois\Versions\Model\Entity\Version[]|\Cake\Datasource\ResultSetInterface|false deleteMany(iterable $entities, $options = [])
* @method \Trois\Versions\Model\Entity\Version[]|\Cake\Datasource\ResultSetInterface deleteManyOrFail(iterable $entities, $options = [])
*
* @mixin \Cake\ORM\Behavior\TimestampBehavior
*/
class VersionsTable extends Table
{
  /**
  * Initialize method
  *
  * @param array $config The configuration for the Table.
  * @return void
  */
  public function initialize(array $config): void
  {
    parent::initialize($config);

    $this->setTable('versions');
    $this->setDisplayField('id');
    $this->setPrimaryKey('id');
    $this->addBehavior('Search.Search');
    $this->searchManager()
    ->add('q', 'Search.Like', [
      'before' => true,
      'after' => true,
      'mode' => 'or',
      'comparison' => 'LIKE',
      'wildcardAny' => '*',
      'wildcardOne' => '?',
      'fields' => ['id']
    ]);
    $this->addBehavior('Timestamp');

    $this->belongsTo('Users', [
      'foreignKey' => 'user_id',
      'className' => 'Trois/Versions.Users',
      'type' => 'LEFT'
    ]);
  }

  /**
  * Default validation rules.
  *
  * @param \Cake\Validation\Validator $validator Validator instance.
  * @return \Cake\Validation\Validator
  */
  public function validationDefault(Validator $validator): Validator
  {
    $validator
    ->uuid('id')
    ->allowEmptyString('id', null, 'create');

    $validator
    ->scalar('model')
    ->maxLength('model', 255)
    ->notEmptyString('model');

    $validator
    ->requirePresence('foreign_key', 'create')
    ->notEmptyString('foreign_key');

    $validator
    ->scalar('locale')
    ->maxLength('locale', 6)
    ->allowEmptyString('locale');

    $validator
    ->scalar('field')
    ->maxLength('field', 255)
    ->requirePresence('field', 'create')
    ->notEmptyString('field');

    $validator
    ->scalar('content')
    ->allowEmptyString('content');

    return $validator;
  }
}
