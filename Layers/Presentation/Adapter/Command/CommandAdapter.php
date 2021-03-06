<?php
namespace Sfynx\CoreBundle\Layers\Presentation\Adapter\Command;

use Sfynx\CoreBundle\Layers\Presentation\Adapter\Generalisation\Interfaces\CommandAdapterInterface;
use Sfynx\CoreBundle\Layers\Presentation\Request\Generalisation\Interfaces\RequestInterface;
use Sfynx\CoreBundle\Layers\Application\Command\Generalisation\Interfaces\CommandInterface;

/**
 * Class CommandAdapter.
 *
 * @category   Sfynx\CoreBundle\Layers
 * @package    Presentation
 * @subpackage Adapter\Command
 */
class CommandAdapter implements CommandAdapterInterface
{
    /** @var  CommandInterface */
    protected $command;

    public function __construct(CommandInterface $command)
    {
        $this->commmand = $command;
    }

    /**
     * @param RequestInterface $request
     * @return CommandInterface
     */
    public function createCommandFromRequest(RequestInterface $request): CommandInterface
    {
        $this->parameters = $request->getRequestParameters();

        foreach ((new \ReflectionObject($this->commmand))->getProperties() as $oProperty) {
            $oProperty->setAccessible(true);
            $value = isset($this->parameters[$oProperty->getName()]) ? $this->parameters[$oProperty->getName()] : $oProperty->getValue($this->commmand);
            $oProperty->setValue($this->commmand, $value);
        }

        return $this->commmand;
    }
}
