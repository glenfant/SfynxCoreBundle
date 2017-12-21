<?php
namespace Sfynx\CoreBundle\Layers\Domain\Service\Manager\Generalisation\Interfaces;

use Sfynx\CoreBundle\Layers\Domain\Repository\Query\QueryRepositoryInterface;
use Sfynx\CoreBundle\Layers\Domain\Repository\Command\CommandRepositoryInterface;
use Sfynx\CoreBundle\Layers\Application\Command\Generalisation\Interfaces\CommandInterface;
use Sfynx\CoreBundle\Layers\Domain\Model\Interfaces\EntityInterface;

/**
 * User Manager Interface
 *
 * @category   Sfynx\CoreBundle\Layers
 * @package    Domain
 * @subpackage Service\Manager\Generalisation
 * @author     Etienne de Longeaux <etienne.delongeaux@gmail.com>
 * @copyright  2015 PI-GROUPE
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version    2.3
 * @link       http://opensource.org/licenses/gpl-license.php
 * @since      2015-02-16
 */
interface ManagerInterface
{
    /**
     * Sets parameter template values.
     *
     * @access protected
     * @return void
     */
    public function setParams(array $option);

    /**
     * Returns the fully qualified class name.
     *
     * @return string
     */
    public function getClass();

    /**
     * Returns the entity name.
     *
     * @return string
     */
    public function getEntityName();

    /**
     * Returns the user's command repository
     *
     * @return CommandRepositoryInterface
     */
    public function getCommandRepository(): CommandRepositoryInterface;

    /**
     * Returns the user's query repository
     *
     * @return QueryRepositoryInterface
     */
    public function getQueryRepository(): QueryRepositoryInterface;

    /**
     * Build and return a new instance of entity from command
     * @param CommandInterface $command
     * @return EntityInterface
     */
    public function newFromCommand(CommandInterface $command): EntityInterface;

    /**
     * Build and return an existed instance of entity from command
     * @param EntityInterface $entity
     * @param CommandInterface $command
     * @return EntityInterface
     */
    public function buildFromCommand(EntityInterface $entity, CommandInterface $command): EntityInterface;

    /**
     * Returns a CommandInterface object representation of the given object, using all its properties.
     *
     * @param CommandInterface $command
     * @param EntityInterface $entity
     * @return CommandInterface
     */
    public function buildFromEntity(CommandInterface $command, EntityInterface $entity): CommandInterface;

    /**
     * Creates an empty entity instance.
     *
     * @return EntityInterface
     */
    public function create(): EntityInterface;

    /**
     * Finds an entity by its primary key / identifier.
     *
     * @param mixed    $id          The identifier.
     * @param int|null $lockMode    One of the \Doctrine\DBAL\LockMode::* constants
     *                              or NULL if no specific lock mode should be used
     *                              during the search.
     * @param int|null $lockVersion The lock version.
     *
     * @return object|null The entity instance or NULL if the entity can not be found.
     */
    public function find($id, $lockMode = null, $lockVersion = null);

    /**
     * Returns a collection with all entities instances.
     *
     * @return \Traversable
     */
    public function findAll();

    /**
     * Finds objects by a set of criteria.
     *
     * Optionally sorting and limiting details can be passed. An implementation may throw
     * an UnexpectedValueException if certain values of the sorting or limiting details are
     * not supported.
     *
     * @param array      $criteria
     * @param array|null $orderBy
     * @param int|null   $limit
     * @param int|null   $offset
     *
     * @return array The objects.
     *
     * @throws \UnexpectedValueException
     */
    public function findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null);

    /**
     * Reloads an entity.
     *
     * @param EntityInterface $entity
     *
     * @return void
     */
    public function reload(EntityInterface $entity);

    /**
     * Updates an entity.
     *
     * @param EntityInterface $entity
     * @param bool $andFlush
     *
     * @return void
     */
    public function update(EntityInterface $entity, $andFlush = true): void ;

    /**
     * Deletes an entity.
     *
     * @param EntityInterface $entity
     *
     * @return void
     */
    public function delete(EntityInterface $entity): void ;
}