<?php
namespace Sfynx\CoreBundle\Layers\Infrastructure\Persistence\Adapter\Generalisation\Orm;

use Gedmo\Tree\Entity\Repository\NestedTreeRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Translation Repository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 *
 * @category   Sfynx\CoreBundle\Layers
 * @package    Infrastructure
 * @subpackage Persistence\Adapter\Generalisation\Orm
 * @abstract
 * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
 * @since 2012-03-09
 */
abstract class AbstractTreeRepository extends NestedTreeRepository
{
    /**
     * Value of the  associated translation class.
     *
     * @var string
     */
    private $_entityTranslationName = "";

    /**
     * @var Query
     */
    public $onChildrenQuery;

    /**
     * List of cached entity configurations
     *
     * @var array
     */
    protected $_configurations = array();

    /**
     * @var ContainerInterface
     */
    protected $_container;

    /**
     * {@inheritdoc}
     */
    public function __construct(EntityManager $em, ClassMetadata $class)
    {
        parent::__construct($em, $class);

        if (isset($this->getClassMetadata()->associationMappings['translations'])
            && !empty($this->getClassMetadata()->associationMappings['translations'])
        ) {
            $this->_entityTranslationName = $this->getClassMetadata()->associationMappings['translations']['targetEntity'];
        }
    }

    /**
     * Find a node by its id
     *
     * @param interger $id
     * @param string   $locale
     * @param string   $result = {'array', 'object'}
     * @param boolean  $INNER_JOIN
     * @param boolean  $FALLBACK
     * @param boolean  $lazy_loading
     *
     * @return array|object
     * @access public
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     */
    public function findNodeOr404($id, $locale, $result = "object", $INNER_JOIN = false, $FALLBACK = true, $lazy_loading = true)
    {
        $query = $this->_em->createQuery("SELECT a FROM {$this->_entityName} a WHERE a.id = :id");
        $query->setParameter('id', $id);
        $query->setMaxResults(1);

        return current($this->findTranslationsByQuery($locale, $query, $result, $INNER_JOIN, $FALLBACK, $lazy_loading));
    }

    /**
     * Find all nodes of the tree by params
     *
     * @param string  $locale
     * @param string  $category
     * @param string  $result = {'array', 'object'}
     * @param boolean $INNER_JOIN
     * @param boolean $enabled
     * @param integer $node
     * @param boolean $is_checkRoles
     * @param boolean $iscache
     *
     * @return object
     * @access public
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     */
    public function getAllTree($locale, $category = '', $result = "object", $INNER_JOIN = false, $enable = true, $node = null, $is_checkRoles = true, $iscache = false)
    {
        if (null !== $node) {
            $query = $this->childrenQueryBuilder($node);
            if (!empty($category)) {
                $query
                ->andWhere('a.category = :category')
                ->setParameter('category', $category);
            }
            if ($enable) {
                $query
                ->andWhere('a.enabled = :enabled')
                ->setParameter('enabled', 1);
            }
        } else {
            $meta   = $this->getClassMetadata();
            $config = $this->listener->getConfiguration($this->_em, $meta->name);
            $query  = $this->_em->createQueryBuilder()
            ->select('a')
            ->from($config['useObjectClass'], 'a')
            ->orderBy('a.root, a.lft', 'ASC');
            if (!empty($category)) {
                $query
                ->where('a.category = :category')
                ->setParameter('category', $category);
                if ($enable) {
                    $query
                    ->andWhere('a.enabled = :enabled')
                    ->setParameter('enabled', 1);
                }
            } elseif (empty($category) && $enable) {
                $query
                ->where('a.enabled = :enabled')
                ->setParameter('enabled', 1);
            }
        }
//        if ($is_checkRoles) {
//            $query = $this->checkRoles($query);
//        }
        if ($result == 'query') {
            return $query;
        } else {
            if ($iscache) {
            	$query = $this->cacheQuery($query->getQuery());
            } else {
            	$query = $query->getQuery();
            }

            return $this->findTranslationsByQuery($locale, $query, $result, $INNER_JOIN);
        }
    }

    /**
     * Find all nodes of the tree by params
     *
     * @param integer $node
     * @param string  $locale
     * @param boolean $INNER_JOIN
     *
     * @return object
     * @access public
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     */
    public function findAllParentChoises($locale, $node = null, $INNER_JOIN = false)
    {
        $dql = "SELECT a FROM {$this->_entityName} a";
        if (!(null === $node)) {
            $subSelect  = "SELECT n FROM {$this->_entityName} n";
            $subSelect .= ' WHERE n.root = '.$node->getRoot();
            $subSelect .= ' AND n.lft BETWEEN '.$node->getLeft().' AND '.$node->getRight();

            $dql .= " WHERE a.id NOT IN ({$subSelect})";
        }
        $q     = $this->_em->createQuery($dql);
        $q     = $this->setTranslatableHints($q, $locale, $INNER_JOIN);
        $nodes = $q->getArrayResult();

        $indexed = [];
        foreach ($nodes as $node) {
            $indexed[$node['id']] = $node['title'];
        }
        return $indexed;
    }

    /**
     * Gets all field values of an entity.
     *
     * @param string $field value of the field table
     *
     * @return array
     * @access public
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     */
    public function getArrayAllByField($field)
    {
        $query = $this->createQueryBuilder('a')
        ->select("a.{$field}")
        ->where('a.enabled = :enabled')
        ->andWhere('a.archived = :archived')
        ->setParameters(array(
            'enabled'  => 1,
            'archived' => 0,
        ));

        $result = array();
        $data   = $query->getQuery()->getArrayResult();
        if ($data && is_array($data) && count($data)) {
            foreach ($data as $row) {
                if (isset($row[$field]) && !empty($row[$field])) {
                    $result[ $row[$field] ] = $row[$field];
                }
            }
        }

        return $result;
    }

    /**
     * Gets all entities by one category.
     *
     * @param string  $category
     * @param integer $MaxResults
     * @param boolean $rootOnly
     *
     * @return QueryBuilder
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     * @since  2012-03-15
     */
    public function getAllByCategory($category = '', $MaxResults = null, $rootOnly = false)
    {
        $query = $this->createQueryBuilder('a')
        ->select('a')
        ->where('a.enabled = :enabled')
        ->andWhere('a.archived = :archived');
        if ($rootOnly && in_array($rootOnly, array('ASC', 'DESC'))) {
            $config = $this->getConfiguration();
            $query->andWhere('a.' . $config['parent'] . " IS NULL")
            ->orderBy('a.' . $config['root'], $rootOnly);
        }
        if (!empty($category)) {
            $query->andWhere('a.category = :cat')
            ->setParameters(array(
                'cat'      => $category,
                'enabled'  => 1,
                'archived' => 0,
            ));
        } else {
            $query->setParameters(array(
                'enabled'  => 1,
                'archived' => 0,
            ));
        }
        if (!(null === $MaxResults)) {
            $query->setMaxResults($MaxResults);
        }

        return $query;
    }

    /**
     * Find all entities by locale
     *
     * @param string   $locale        Locale value
     * @param string   $result        ['array', 'object']
     * @param boolean  $INNER_JOIN
     * @param boolean  $is_checkRoles
     * @param boolean  $FALLBACK
     * @param boolean  $lazy_loading
     *
     * @return object
     * @access public
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     */
    public function getEntities($locale, $result = "object", $INNER_JOIN = true, $is_checkRoles = true, $FALLBACK = true, $lazy_loading = true)
    {
        $query = $this->_em->createQueryBuilder()
        ->select('a')
        ->from($this->_entityName, 'c')
        ;
        if (!(null === $this->basedOnNode)) {
            $query->where($qb->expr()->notIn(
                'a.id',
                $this->_em
                ->createQueryBuilder()
                ->select('n')
                ->from($this->_entityName, 'n')
                ->where('n.root = '.$this->basedOnNode->getRoot())
                ->andWhere($qb->expr()->between(
                        'n.lft',
                        $this->basedOnNode->getLeft(),
                        $this->basedOnNode->getRight()
                ))
                ->getDQL()
            ));
        }
//        if ($is_checkRoles) {
//            $query = $this->checkRoles($query);
//        }

        return $this->findTranslationsByQuery($locale, $query->getQuery(), $result, $INNER_JOIN, $FALLBACK, $lazy_loading);
    }

    /**
     * Find all entities of the entity by list of ids
     *
     * @param interger $identifier
     * @param array    $parameters    array of all id values
     * @param string   $locale        Locale value
     * @param string   $result        ['array', 'object']
     * @param boolean  $INNER_JOIN
     * @param boolean  $is_checkRoles
     * @param boolean  $FALLBACK
     * @param boolean  $lazy_loading
     *
     * @return object
     * @access public
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     */
    public function getEntitiesByIds($identifier, array $parameters, $locale, $result = "object", $INNER_JOIN = true, $is_checkRoles = true, $FALLBACK = true, $lazy_loading = true)
    {
        $query = $this->_em->createQueryBuilder()
        ->select('a')
        ->from($this->_entityName, 'a')
        ->where($query->expr()->in(
            'a.'.$identifier,
            ':ids'
        ))
        ->setParameter('ids', $parameters, Connection::PARAM_INT_ARRAY)
        ;
//        if ($is_checkRoles) {
//            $query = $this->checkRoles($query);
//        }

        return $this->findTranslationsByQuery($locale, $query->getQuery(), $result, $INNER_JOIN, $FALLBACK, $lazy_loading);
    }

    /**
     * Find a translation field of an entity by its id
     *
     * @param string  $locale
     * @param array   $fields
     * @param bool    $INNER_JOIN
     *
     * @return object
     * @access public
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     */
    public function getContentByField($locale, array $fields, $INNER_JOIN = false)
    {
        $query    = $this->_em->createQuery("SELECT p FROM {$this->_entityTranslationName} p  WHERE p.locale = :locale and p.field = :field and p.content = :content ");
        $query->setParameter('locale', $locale);
        $query->setParameter('field', array_keys($fields['content_search']));
        $query->setParameter('content', array_values($fields['content_search']));
        $query->setMaxResults(1);
        $entities = $query->getResult();
        if (!(null === $entities)){
            $entity = current($entities);
            if (is_object($entity)){
                $id    = $entity->getObject()->getId();
                $query = $this->_em->createQuery("SELECT p FROM {$this->_entityTranslationName} p  WHERE p.locale = :locale and p.field = :field and p.object = :objectId");
                $query->setParameter('locale', $locale);
                $query->setParameter('objectId', $id);
                $query->setParameter('field', $fields['field_result']);
                $query->setMaxResults(1);
                $entities = $query->getResult();
                if (!(null === $entities) && (count($entities)>=1) ){
                    return current($entities);
                }
                return null;
            } else {
                return null;
            }
        }
        return null;
        //         $dql = <<<___SQL
        //   SELECT a
        //   FROM {$this->_entityName} a
        //   WHERE a.slug = '{$slug}'
        // ___SQL;

        //         $query  = $this->_em->createQuery($dql);
        //         $result = $this->findTranslationsByQuery($locale, $query, $result, $INNER_JOIN);


        //         print_r(count($result));exit;

        //         return current($result);
    }

    /**
     * Find a translation of an entity by its id
     *
     * @param string  $locale
     * @param array   $fields
     * @param string  $result     ['array', 'object']
     * @param boolean $INNER_JOIN
     *
     * @return null|object
     * @access public
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     */
    public function getEntityByField($locale, array $fields, $result = "object", $INNER_JOIN = false)
    {
        $query    = $this->_em->createQuery("SELECT p FROM {$this->_entityTranslationName} p  WHERE p.locale = :locale and p.field = :field and p.content = :content ");
        $query->setParameter('locale', $locale);
        $query->setParameter('field', array_keys($fields['content_search']));
        $query->setParameter('content', array_values($fields['content_search']));
        $query->setMaxResults(1);
        $entities = $query->getResult();
        if (!(null === $entities)){
            $entity = current($entities);
            if (is_object($entity)){
                $id        = $entity->getObject()->getId();
                return $this->findOneByEntity($locale, $id, $result, $INNER_JOIN);
            }
            return null;
        }
        return null;
    }

    ////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////
    /**
     * Will do reordering based on current translations
     */
    public function childrenQuery($node = null, $direct = false, $sortByField = null, $direction = 'ASC', $includeNode = false)
    {
        $q = parent::childrenQuery($node, $direct, $sortByField, $direction, $includeNode);
        if ($this->onChildrenQuery instanceof Closure) {
            $c = &$this->onChildrenQuery;
            $c($q);
        }
        return $q;
    }

    /**
     * Counts the children of given TreeNode
     *
     * @param object $node - if null counts all records in tree
     * @param boolean $direct - true to count only direct children
     * @return integer
     */
    public function childCount($node = null, $direct = false)
    {
        $count = 0;
        $meta = $this->getClassMetadata();
        $nodeId = $meta->getSingleIdentifierFieldName();
        $config = $this->getConfiguration();
        if (null !== $node) {
            if ($direct) {
                $id = $meta->getReflectionProperty($nodeId)->getValue($node);
                $qb = $this->_em->createQueryBuilder();
                $qb->select('COUNT(a.' . $nodeId . ')')
                    ->from($this->_entityName, 'a')
                    ->where('a.' . $config['parent'] . ' = ' . $id);

                $q = $qb->getQuery();
                $count = intval($q->getSingleScalarResult());
            } else {
                $left = $meta->getReflectionProperty($config['left'])->getValue($node);
                $right = $meta->getReflectionProperty($config['right'])->getValue($node);
                if ($left && $right) {
                    $count = ($right - $left - 1) / 2;
                }
            }
        } else {
            $dql = "SELECT COUNT(a.{$nodeId}) FROM {$this->_entityName} a";
            if ($direct) {
                $dql .= ' WHERE a.' . $config['parent'] . ' IS NULL';
            }
            $q = $this->_em->createQuery($dql);
            $count = intval($q->getSingleScalarResult());
        }
        return $count;
    }

    /**
     * Get list of children followed by given $node
     *
     * @param object $node - if null, all tree nodes will be taken
     * @param boolean $direct - true to take only direct children
     * @param string $sortByField - field name to sort by
     * @param string $direction - sort direction : "ASC" or "DESC"
     * @return array - list of given $node children, null on failure
     */
    public function children($node = null, $direct = false, $sortByField = null, $direction = 'ASC', $includeNode = false)
    {
        $meta = $this->getClassMetadata();
        $config = $this->getConfiguration();

        $qb = $this->_em->createQueryBuilder();
        $qb->select('a')
            ->from($this->_entityName, 'a');
        if ($node !== null) {
            if ($direct) {
                $nodeId = $meta->getSingleIdentifierFieldName();
                $id = $meta->getReflectionProperty($nodeId)->getValue($node);
                $qb->where('a.' . $config['parent'] . ' = ' . $id);
            } else {
                $left = $meta->getReflectionProperty($config['left'])->getValue($node);
                $right = $meta->getReflectionProperty($config['right'])->getValue($node);
                if ($left && $right) {
                    $qb->where('a.' . $config['right'] . " < {$right}")
                        ->andWhere('a.' . $config['left'] . " > {$left}");
                }
            }
        } else {
            if ($direct) {
                $qb->where('a.' . $config['parent'] . ' IS NULL');
            }
        }
        if (!$sortByField) {
            $qb->orderBy('a.' . $config['left'], 'ASC');
        } else {
            if ($meta->hasField($sortByField) && in_array(strtolower($direction), array('asc', 'desc'))) {
                $qb->orderBy('a.' . $sortByField, $direction);
            } else {
                throw new \RuntimeException("Invalid sort options specified: field - {$sortByField}, direction - {$direction}");
            }
        }
        $q = $qb->getQuery();
        $q->useResultCache(false);
        $q->useQueryCache(false);
        return $q->getResult(Query::HYDRATE_OBJECT);
    }

    /**
     * Generally loads configuration from cache
     *
     * @throws RuntimeException if no configuration for class found
     * @return array
     */
    public function getConfiguration() {
        $config = array();
        if (isset($this->_configurations[$this->_entityName])) {
            $config = $this->_configurations[$this->_entityName];
        } else {
            $cacheDriver = $this->_em->getMetadataFactory()->getCacheDriver();
            $cacheId = \Gedmo\Mapping\ExtensionMetadataFactory::getCacheId(
                $this->_entityName,
                'Gedmo\Tree'
            );
            if (($cached = $cacheDriver->fetch($cacheId)) !== false) {
                $this->_configurations[$this->_entityName] = $cached;
                $config = $cached;
            }
        }
        if (!$config) {
            throw new \RuntimeException("TreeNodeRepository: this repository cannot be used on {$this->_entityName} without Tree metadata");
        }
        return $config;
    }

    /**
     * Synchronize the tree with given conditions
     *
     * @param array $config
     * @param integer $shift
     * @param string $dir
     * @param string $conditions
     * @param string $field
     * @return void
     */
    protected function _sync($config, $shift, $dir, $conditions, $field = 'both')
    {
        if ($field == 'both') {
            $this->_sync($config, $shift, $dir, $conditions, $config['left']);
            $field = $config['right'];
        }

        $dql = "UPDATE {$this->_entityName} a";
        $dql .= " SET a.{$field} = a.{$field} {$dir} {$shift}";
        $dql .= " WHERE a.{$field} {$conditions}";

        $q = $this->_em->createQuery($dql);
        return $q->getSingleScalarResult();
    }

    /**
     * Synchronize tree according to Node`s parent Node
     *
     * @param array $config
     * @param Node $parent
     * @param Node $node
     * @return void
     */
    protected function _adjustNodeWithParent($config, $parent, $node)
    {
        $edge = $this->_getTreeEdge($config);
        $meta = $this->getClassMetadata();
        $leftField = $config['left'];
        $rightField = $config['right'];
        $parentField = $config['parent'];

        $leftValue = $meta->getReflectionProperty($leftField)->getValue($node);
        $rightValue = $meta->getReflectionProperty($rightField)->getValue($node);
        if ($parent === null) {
            $this->_sync($config, $edge - $leftValue + 1, '+', 'BETWEEN ' . $leftValue . ' AND ' . $rightValue);
            $this->_sync($config, $rightValue - $leftValue + 1, '-', '> ' . $leftValue);
        } else {
            // need to refresh the parent to get up to date left and right
            $this->_em->refresh($parent);
            $parentLeftValue = $meta->getReflectionProperty($leftField)->getValue($parent);
            $parentRightValue = $meta->getReflectionProperty($rightField)->getValue($parent);
            if ($leftValue < $parentLeftValue && $parentRightValue < $rightValue) {
                return;
            }
            if (empty($leftValue) && empty($rightValue)) {
                $this->_sync($config, 2, '+', '>= ' . $parentRightValue);
                // cannot schedule this update if other Nodes pending
                $qb = $this->_em->createQueryBuilder();
                $qb->update($this->_entityName, 'a')
                    ->set('a.' . $leftField, $parentRightValue)
                    ->set('a.' . $rightField, $parentRightValue + 1);
                $entityIdentifiers = $meta->getIdentifierValues($node);
                foreach ($entityIdentifiers as $field => $value) {
                    if (strlen($value)) {
                        $qb->where('a.' . $field . ' = ' . $value);
                    }
                }
                $q = $qb->getQuery();
                $q->getSingleScalarResult();
            } else {
                $this->_sync($config, $edge - $leftValue + 1, '+', 'BETWEEN ' . $leftValue . ' AND ' . $rightValue);
                $diff = $rightValue - $leftValue + 1;

                if ($leftValue > $parentLeftValue) {
                    if ($rightValue < $parentRightValue) {
                        $this->_sync($config, $diff, '-', 'BETWEEN ' . $rightValue . ' AND ' . ($parentRightValue - 1));
                        $this->_sync($config, $edge - $parentRightValue + $diff + 1, '-', '> ' . $edge);
                    } else {
                        $this->_sync($config, $diff, '+', 'BETWEEN ' . $parentRightValue . ' AND ' . $rightValue);
                        $this->_sync($config, $edge - $parentRightValue + 1, '-', '> ' . $edge);
                    }
                } else {
                    $this->_sync($config, $diff, '-', 'BETWEEN ' . $rightValue . ' AND ' . ($parentRightValue - 1));
                    $this->_sync($config, $edge - $parentRightValue + $diff + 1, '-', '> ' . $edge);
                }
            }
        }
    }

    /**
     * Get the edge of tree
     *
     * @param array $config
     * @return integer
     */
    protected function _getTreeEdge($config)
    {
        $q = $this->_em->createQuery("SELECT MAX(a.{$config['right']}) FROM {$this->_entityName} a");
        $q->useResultCache(false);
        $q->useQueryCache(false);
        $right = $q->getSingleScalarResult();
        return intval($right);
    }

    /**
     * Tries to recover the tree
     *
     * @throws Exception if something fails in transaction
     * @return void
     *
     * {@inheritDoc}
     */
    public function recover()
    {
        if ($this->verify() === true) {
            return;
        }

        $meta = $this->getClassMetadata();
        $config = $this->getConfiguration();

        $identifier = $meta->getSingleIdentifierFieldName();
        $leftField = $config['left'];
        $rightField = $config['right'];
        $parentField = $config['parent'];

        $count = 1;
        $dql = "SELECT a.{$identifier} FROM {$this->_entityName} a";
        $dql .= " ORDER BY a.{$leftField} ASC";
        $q = $this->_em->createQuery($dql);
        $nodes = $q->getArrayResult();
        // process updates in transaction
        $this->_em->getConnection()->beginTransaction();
        try {
            foreach ($nodes as $node) {
                $left = $count++;
                $right = $count++;
                $dql = "UPDATE {$this->_entityName} a";
                $dql .= " SET a.{$leftField} = {$left},";
                $dql .= " a.{$rightField} = {$right}";
                $dql .= " WHERE a.{$identifier} = {$node[$identifier]}";
                $q = $this->_em->createQuery($dql);
                $q->getSingleScalarResult();
            }
            foreach ($nodes as $node) {
                $node = $this->_em->getReference($this->_entityName, $node[$identifier]);
                $this->_em->refresh($node);
                $parent = $meta->getReflectionProperty($parentField)->getValue($node);
                $this->_adjustNodeWithParent($config, $parent, $node);
            }
            $this->_em->getConnection()->commit();
        } catch (\Exception $e) {
            $this->_em->close();
            $this->_em->getConnection()->rollback();
            throw $e;
        }
    }

//     /**
//      * Get the Tree path of Nodes by given $node
//      *
//      * @param object $node
//      * @return array - list of Nodes in path
//      */
//     public function getPath($node)
//     {
//         $result = array();
//         $meta = $this->getClassMetadata();
//         $config = $this->getConfiguration();

//         $left = $meta->getReflectionProperty($config['left'])->getValue($node);
//         $right = $meta->getReflectionProperty($config['right'])->getValue($node);
//         if ($left && $right) {
//             $qb = $this->_em->createQueryBuilder();
//             $qb->select('a')
//             ->from($this->_entityName, 'a')
//             ->where('a.' . $config['left'] . " <= :left")
//             ->andWhere('a.' . $config['right'] . " >= :right")
//             ->orderBy('a.' . $config['left'], 'ASC');
//             $q = $qb->getQuery();
//             $result = $q->execute(
//                     compact('left', 'right'),
//                     Query::HYDRATE_OBJECT
//             );
//         }
//         return $result;
//     }

//     /**
//      * Get list of leaf nodes of the tree
//      *
//      * @param string $sortByField - field name to sort by
//      * @param string $direction - sort direction : "ASC" or "DESC"
//      * @return array - list of given $node children, null on failure
//      */
//     public function getLeafs($root = null, $sortByField = null, $direction = 'ASC')
//     {
//         $meta = $this->getClassMetadata();
//         $config = $this->getConfiguration();

//         $qb = $this->_em->createQueryBuilder();
//         $qb->select('a')
//         ->from($this->_entityName, 'a')
//         ->where('a.' . $config['right'] . ' = 1 + a.' . $config['left']);
//         if (!$sortByField) {
//             $qb->orderBy('a.' . $config['left'], 'ASC');
//         } else {
//             if ($meta->hasField($sortByField) && in_array(strtolower($direction), array('asc', 'desc'))) {
//                 $qb->orderBy('a.' . $sortByField, $direction);
//             } else {
//                 throw new \RuntimeException("Invalid sort options specified: field - {$sortByField}, direction - {$direction}");
//             }
//         }
//         $q = $qb->getQuery();
//         return $q->getResult(Query::HYDRATE_OBJECT);
//     }

    /**
     * Move the node up in the same level
     *
     * @param object $node
     * @param mixed $number
     *         integer - number of positions to shift
     *         boolean - true shift till first position
     * @throws Exception if something fails in transaction
     * @return boolean - true if shifted
     */
    public function moveUp($node, $number = 1)
    {
        $meta = $this->getClassMetadata();
        $config = $this->getConfiguration();
        if (!$number) {
            return false;
        }

        $parent = $meta->getReflectionProperty($config['parent'])->getValue($node);
        $left = $meta->getReflectionProperty($config['left'])->getValue($node);
        if ($parent) {
            $this->_em->refresh($parent);
            $parentLeft = $meta->getReflectionProperty($config['left'])->getValue($parent);
            if (($left - 1) == $parentLeft) {
                return false;
            }
        }

        $dql = "SELECT a FROM {$this->_entityName} a";
        $dql .= ' WHERE a.' . $config['right'] . ' = ' . ($left - 1);
        $q = $this->_em->createQuery($dql);
        $q->setMaxResults(1);
        $result = $q->getResult(Query::HYDRATE_OBJECT);
        $previousSiblingNode = count($result) ? array_shift($result) : null;

        if (!$previousSiblingNode) {
            return false;
        }
        // this one is very important because if em is not cleared
        // it loads node from memory without refresh
        $this->_em->refresh($previousSiblingNode);

        $right = $meta->getReflectionProperty($config['right'])->getValue($node);
        $previousLeft = $meta->getReflectionProperty($config['left'])->getValue($previousSiblingNode);
        $previousRight = $meta->getReflectionProperty($config['right'])->getValue($previousSiblingNode);
        $edge = $this->_getTreeEdge($config);
        // process updates in transaction
        $this->_em->getConnection()->beginTransaction();
        try {
            $this->_sync($config, $edge - $previousLeft +1, '+', 'BETWEEN ' . $previousLeft . ' AND ' . $previousRight);
            $this->_sync($config, $left - $previousLeft, '-', 'BETWEEN ' .$left . ' AND ' . $right);
            $this->_sync($config, $edge - $previousLeft - ($right - $left), '-', '> ' . $edge);
            $this->_em->getConnection()->commit();
        } catch (\Exception $e) {
            $this->_em->close();
            $this->_em->getConnection()->rollback();
            throw $e;
        }
        if (is_int($number)) {
            $number--;
        }
        if ($number) {
            $this->_em->refresh($node);
            $this->moveUp($node, $number);
        }
        return true;
    }

    /**
     * Move the node down in the same level
     *
     * @param object $node
     * @param mixed $number
     *         integer - number of positions to shift
     *         boolean - true shift till last position
     * @throws Exception if something fails in transaction
     * @return boolean - true if shifted
     */
    public function moveDown($node, $number = 1)
    {
        $meta = $this->getClassMetadata();
        $config = $this->getConfiguration();
        if (!$number) {
            return false;
        }

        $parent = $meta->getReflectionProperty($config['parent'])->getValue($node);
        $right = $meta->getReflectionProperty($config['right'])->getValue($node);

        if ($parent) {
            $this->_em->refresh($parent);
            $parentRight = $meta->getReflectionProperty($config['right'])->getValue($parent);
            if (($right + 1) == $parentRight) {
                return false;
            }
        }
        $dql = "SELECT a FROM {$this->_entityName} a";
        $dql .= ' WHERE a.' . $config['left'] . ' = ' . ($right + 1);
        $q = $this->_em->createQuery($dql);
        $q->setMaxResults(1);
        $result = $q->getResult(Query::HYDRATE_OBJECT);
        $nextSiblingNode = count($result) ? array_shift($result) : null;

//         if (!$nextSiblingNode) {
//             return false;
//         }

        // this one is very important because if em is not cleared
        // it loads node from memory without refresh
        //$this->_em->refresh($nextSiblingNode);

        $left = $meta->getReflectionProperty($config['left'])->getValue($node);
        $nextLeft = $meta->getReflectionProperty($config['left'])->getValue($nextSiblingNode);
        $nextRight = $meta->getReflectionProperty($config['right'])->getValue($nextSiblingNode);
        $edge = $this->_getTreeEdge($config);
        // process updates in transaction
        $this->_em->getConnection()->beginTransaction();
        try {
            $this->_sync($config, $edge - $left + 1, '+', 'BETWEEN ' . $left . ' AND ' . $right);
            $this->_sync($config, $nextLeft - $left, '-', 'BETWEEN ' . $nextLeft . ' AND ' . $nextRight);
            $this->_sync($config, $edge - $left - ($nextRight - $nextLeft), '-', ' > ' . $edge);
            $this->_em->getConnection()->commit();
        } catch (\Exception $e) {
            $this->_em->close();
            $this->_em->getConnection()->rollback();
            throw $e;
        }
        if (is_int($number)) {
            $number--;
        }
        if ($number) {
            $this->_em->refresh($node);
            $this->moveDown($node, $number);
        }
        return true;
    }

    /**
     * Move the node up in the same level
     *
     * @param object $oldRoot
     * @param object $newRoot
     * @throws Exception if something fails in transaction
     * @return boolean - true if shifted
     */
    public function moveRoot($oldRoot, $newRoot)
    {
        $meta     = $this->getClassMetadata();
        $config = $this->getConfiguration();
        $field  = $config["root"];
        // process updates in transaction
        $this->_em->getConnection()->beginTransaction();
        try {
            $dql = "UPDATE {$this->_entityName} a";
            $dql .= " SET a.{$field} = {$newRoot}";
            $dql .= " WHERE a.{$field} = {$oldRoot}";

            $q = $this->_em->createQuery($dql);
            $q->getSingleScalarResult();
            $this->_em->getConnection()->commit();

            return true;
        } catch (\Exception $e) {
            $this->_em->close();
            $this->_em->getConnection()->rollback();
            throw $e;

            return false;
        }
    }

    /**
     * Return all routes names of all childs of a tree a.
     *
     * @param string $name
     * @param string $type        ['array', 'string']
     * @return Object
     */
    public function findChildsRouteByParentId($id, $locale, $type = 'array')
    {
        $routesnames = null;
        if (!empty($id)){
            $node   = $this->findNodeOr404($id, $locale,'object');
            $query  = $this->childrenQuery($node);
            $childs = $query->getResult();

            if ( method_exists($node, 'getPage') && ($node->getPage() InstanceOf \PiApp\AdminBundle\Entity\Page) ) {
                $routesnames[]     = $node->getPage()->getRouteName();
            }
            if (is_array($childs)){
                foreach($childs as $key => $child){
                    if (method_exists($child, 'getPage')  && ($child->getPage() instanceof \PiApp\AdminBundle\Entity\Page) ){
                        $routesnames[]  = $child->getPage()->getRouteName();
                    }
                }
            }
            if ($type == 'string'){
                if (!is_null($routesnames))
                    $routesnames = implode(':', $routesnames);
            }
        }

        return $routesnames;
    }
}
