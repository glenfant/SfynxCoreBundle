<?php
namespace Sfynx\CoreBundle\Layers\Domain\Specification;

use stdClass;
use Sfynx\SpecificationBundle\Specification\AbstractSpecification;
use Sfynx\CoreBundle\Layers\Application\Common\Generalisation\Interfaces\HandlerInterface;

/**
 * Class SpecIsObjectCreatedWithHandlerInterface
 *
 * @category Sfynx\CoreBundle\Layers
 * @package Domain
 * @subpackage Specification
 * @author     Etienne de Longeaux <etienne.delongeaux@gmail.com>
 */
class SpecIsObjectCreatedWithHandlerInterface extends AbstractSpecification
{
    /**
     * return true if we are in the editing process
     *
     * @param stdClass $object
     * @return bool
     */
    public function isSatisfiedBy(stdClass $object): bool
    {
        return property_exists($object, 'handler') &&
            ($object->handler instanceof HandlerInterface);
    }
}
