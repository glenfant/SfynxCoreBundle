<?php
namespace Sfynx\CoreBundle\Layers\Domain\Repository\Query;

use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\EntityManagerInterface;

use Sfynx\CoreBundle\Layers\Infrastructure\Cache\CacheQuery;
use Sfynx\CoreBundle\Layers\Domain\Repository\Query\GeneralRepositoryInterface;
use Sfynx\CoreBundle\Layers\Domain\Repository\Query\TranslationRepositoryInterface;
use Sfynx\CoreBundle\Layers\Infrastructure\Persistence\Repository\Generalisation\Interfaces\ResultInterface;

/**
 * Query Repository Interface
 *
 * @category   Sfynx\CoreBundle\Layers
 * @package    Domain
 * @subpackage Repository\Query
 * @author     Etienne de Longeaux <etienne.delongeaux@gmail.com>
 * @copyright  2015 PI-GROUPE
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version    2.3
 * @link       http://opensource.org/licenses/gpl-license.php
 * @since      2015-02-16
 */
interface QueryRepositoryInterface extends GeneralRepositoryInterface,TranslationRepositoryInterface,ObjectRepository
{
    /**
     * @return ResultInterface
     */
    public function Result(?Query $query): ResultInterface;

    /**
     * @return CacheQuery
     */
    public function getCacheFactory(): CacheQuery;

    /**
     * @return EntityManagerInterface
     */
    public function getEm(): EntityManagerInterface;

    /**
     * @param string    entityName    nom de l'entité
     * @param integer   id    id de la référence demandée
     */
    public function getReference($id);
}