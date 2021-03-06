<?php
namespace Sfynx\DddBundle\Layer\Domain\ValueObject;

use Sfynx\CoreBundle\Layers\Domain\ValueObject\Generalisation\AbstractVO;
use Sfynx\CoreBundle\Layers\Infrastructure\Exception\DomainException;
use Ramsey\Uuid\Uuid as BaseUuid;
use JMS\Serializer\Annotation\Since;

/**
 * Abstract Class AbstractVO
 *
 * @category Sfynx\DddBundle\Layer
 * @package Domain
 * @subpackage ValueObject
 * @abstract
 */
class IdVO extends AbstractVO
{
    /**
     * @Since("1")
     * @var string
     */
    protected $id;

    /**
     * @param  string $id
     * @throws DomainException
     */
    public function __construct($id = null)
    {
        $uuid_str = BaseUuid::uuid4();
        if (null !== $id) {
            $pattern = '/'.BaseUuid::VALID_PATTERN.'/';
            if (! \preg_match($pattern, $id)) {
                throw new DomainException($id, array('UUID string'));
            }
            $uuid_str = $id;
        }
        $this->id = \strval($uuid_str);
    }


    /**
     * @param $id
     */
    protected function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }
}
