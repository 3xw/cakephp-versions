<?php
namespace Trois\Versions\Model\Behavior;

use ArrayObject;

use Cake\Event\Event;
use Cake\Datasource\EntityInterface;
use Cake\ORM\Behavior;
use Cake\ORM\Table;
use Cake\ORM\Query;
use Cake\I18n\I18n;
use Cake\Core\Configure;
use Cake\Utility\Text;
use Cake\Datasource\ModelAwareTrait;
use Cake\Routing\Router;
use Cake\Utility\Hash;

use Trois\Versions\Model\Entity\EntityVersion;

/**
* Versions behavior
*/
class VersionsBehavior extends Behavior
{

  use ModelAwareTrait;

  /**
  * Default configuration.
  *
  * @var array
  */
  protected $_defaultConfig = [
    'translate' => false,
    'retention' => [
      'max_amount' => 10, // 0 => no limit
      'max_period' => '24 months', // 0 => no limit
    ],
    'fields' => [],
    'version_field' => '_versions',
  ];

  public function initialize(array $config): void
  {
    parent::initialize($config);
    $this->loadModel('Trois/Versions.Versions');
  }

  public function cleanVersions(): int
  {
    $total = 0;
    $conditions = [
      'model' => $this->table()->getAlias()
    ];

    if($period = $this->getConfig('retention.max_period'))
    {
      $total += $this->Versions->deleteAll(array_merge($conditions, ['created <' => (new \DateTime)->modify("-$period")->format('Y-m-d H:i:s')]));
    }

    if($amount = $this->getConfig('retention.max_amount'))
    {
      $query = $this->Versions->find();
      $targets = $query->select([
        'foreign_key', 'locale','model','total_items' => $query->func()->count('foreign_key')
      ])
      ->group(['foreign_key','locale'])
      ->having(['COUNT(foreign_key) >' => $amount])
      ->enableAutoFields(false)
      ->toArray();

      foreach($targets  as $t)
      {
        $list = $this->Versions->find('list')
        ->where([
          'foreign_key' => $t->foreign_key,
          'locale' => $t->locale,
          'model' => $t->model,
        ])
        ->order(['created' => 'DESC'])
        ->limit(1000)
        ->offset($amount)
        ->toArray();

        $total += $this->Versions->deleteAll(['id IN' => array_keys($list)]);
      }
    }
    return $total;
  }

  public function findVersions(Query $query, array $options)
  {
    $prop = $this->getConfig('version_field');

    // link models
    $this->table()->hasMany('Versions', [
      'className' => 'Trois/Versions.Versions',
      'foreignKey' => 'foreign_key',
      'conditions' => ['Versions.model' => $this->table()->getAlias()],
      'propertyName' => $prop,
      'sort' => ['Versions.created' => 'DESC']
    ]);
    $query->contain(['Versions']);

    // options amount
    if(!empty($options['amount']) && $amount = $options['amount']) $query->matching('Versions', function ($q) use($amount) {
      return $q->limit($amount);
    });

    // options amount
    if(!empty($options['period']) && $period = $options['period']) $query->matching('Versions', function ($q) use($period) {
      return $q->where(['Versions.created >=' => (new \DateTime)->modify("-$period")->format('Y-m-d H:i:s')]);
    });

    $query->mapReduce(
      function ($entity, $key, $mr) use ($prop){
        $versions = $entity->{$prop};
        $entity->unset($prop);
        $this->attachVersions($entity, $versions);
        $mr->emitIntermediate($entity, $key);
      },
      function ($entities, $key, $mr) { $mr->emit($entities[0], $key); }
    );

    return $query;
  }

  public function afterSave(Event $event, EntityInterface $entity, ArrayObject $options)
  {
    // try save version of each fields
    if(!$versions = $this->createVersions($entity, $this->getConfig('fields')))
    {
      $event->stopPropagation();
      return false;
    }

    // attach current version
    $this->attachVersions($entity, $versions);

    return $entity;
  }

  public function afterDelete(Event $event, EntityInterface $entity, ArrayObject $options)
  {
    // set conditions
    $conditions = [
      'model' => $this->table()->getAlias(),
      'foreign_key' => $entity->{ $this->table()->getPrimarykey() },
    ];

    // count rows
    if(!$count = $this->Versions->find()->where($conditions)->count()) return $entity;

    // delete rows and check
    if($this->Versions->deleteAll($conditions) < $count)
    {
      $event->stopPropagation();
      return false;
    }

    return $entity;
  }

  protected function attachVersions(EntityInterface $entity, $versions)
  {
    $structuredVersions = $this->toStructVersionsArray($versions);
    foreach($structuredVersions as $datetime => &$localesArray)
    {
      // create entity from current entity state
      $e = $this->table()->newEntity($entity->toArray());

      // modify entity with version state
      foreach($localesArray[Configure::read('App.defaultLocale')] as $vf) $e->set($vf->field, $vf->content);

      // create EntityVersion
      $entityVersion = new EntityVersion([
        'id' => $vf->version_id,
        'created' => $vf->created,
        'owned_by' => $vf->user?? $vf->user_id,
        'entity' => $e
      ]);

      // clean first set
      unset($localesArray[Configure::read('App.defaultLocale')]);

      // i18n
      $eI18n = $this->table()->newEntity($e->toArray());
      $trans = [];
      foreach($localesArray as $locale => $verionFiledsArray)
      {
        $trans[$locale] = $this->table()->newEntity($eI18n->toArray());
        foreach($verionFiledsArray as $vf) $trans[$locale]->set($vf->field, $vf->content);
      }
      $e->set('_translations', $trans);

      $localesArray = $entityVersion;
    }

    $entity->set($this->getConfig('version_field'), $structuredVersions);
  }

  protected function toStructVersionsArray(array $versions)
  {
    $sv = [];
    foreach($versions as $version)
    {
      $v = is_array($version)? $this->Versions->newEntity($version): $version;
      $created = $v->created->format('Y-m-d H:i:s');
      $path = "$created.$v->locale";

      // create dim created.locale
      if(Hash::check($sv, $path)) $sv = Hash::merge($sv, Hash::expand([$path => []]));

      // add field for created.locale
      $sv[$created][$v->locale][] = $v;
    }

    // sort array by created DESC
    krsort($sv);

    return $sv;
  }

  protected function tryGetUserId()
  {
    if(!Router::getRequest()) return null;
    if(!$identity = Router::getRequest()->getAttribute('identity')) return null;
    if(empty($identity['id'])) return null;
    return $identity['id'];
  }

  protected function createVersions(EntityInterface $entity, $fields)
  {
    $versions = [];
    $toMerge = [
      'created' => new \DateTime(),
      'version_id' => Text::uuid(),
      'model' => $this->table()->getAlias(),
      'foreign_key' => $entity->{ $this->table()->getPrimarykey() },
      'user_id' => $this->tryGetUserId()
    ];

    foreach ($fields as $field)
    {
      // regular
      $versions[] = $this->createVerionForField($field, $entity->get($field), Configure::read('App.defaultLocale'), $toMerge);

      // translation
      if($this->getConfig('translate') && !empty($entity->get('_translations')))
      {
        foreach ($entity->get('_translations') as $locale => $i18nEntity) $versions[] = $this->createVerionForField($field, $i18nEntity->get($field), $locale, $toMerge);
      }
    }

    return $this->Versions->saveMany($versions);
  }

  protected function createVerionForField($field, $value, $locale, $toMerge = [])
  {
    return $this->Versions->newEntity(array_merge($toMerge,[
      'field' => $field,
      'content' => $value,
      'locale' => $locale
    ]));
  }
}
