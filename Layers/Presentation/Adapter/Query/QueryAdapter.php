<?php
namespace Sfynx\CoreBundle\Layers\Presentation\Adapter\Query;

use Sfynx\CoreBundle\Layers\Presentation\Adapter\Generalisation\Interfaces\QueryAdapterInterface;
use Sfynx\CoreBundle\Layers\Presentation\Request\Generalisation\Interfaces\RequestInterface;
use Sfynx\CoreBundle\Layers\Application\Query\Generalisation\Interfaces\QueryInterface;

/**
 * Class QueryAdapter.
 *
 * @category   Sfynx\CoreBundle\Layers
 * @package    Presentation
 * @subpackage Adapter\Query
 */
class QueryAdapter implements QueryAdapterInterface
{
    /** @var  QueryInterface */
    protected $query;

    public function __construct(QueryInterface $query)
    {
        $this->query = $query;
    }

    /**
     * @param RequestInterface $request
     * @return QueryInterface
     */
    public function createQueryFromRequest(RequestInterface $request): QueryInterface
    {
        $this->parameters = $request->getRequestParameters();

        foreach ((new \ReflectionObject($this->query))->getProperties() as $oProperty) {
            $oProperty->setAccessible(true);
            $value = isset($this->parameters[$oProperty->getName()]) ? $this->parameters[$oProperty->getName()] : $oProperty->getValue($this->query);
            $oProperty->setValue($this->query, $value);
        }

        return $this->query;
    }
}