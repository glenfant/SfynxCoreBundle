<?php
namespace Sfynx\CoreBundle\Generator\Domain\Templater\Templater_\Architecture\Domain\WorkflowObserver;

use Sfynx\CoreBundle\Generator\Domain\Widget\Generalisation\Interfaces\WidgetInterface;
use Sfynx\CoreBundle\Generator\Domain\Templater\Generalisation\Interfaces\TemplaterInterface;
use Sfynx\CoreBundle\Generator\Domain\Templater\Generalisation\AbstractTemplater;
use Sfynx\CoreBundle\Generator\Domain\Report\ReporterObservable;

/**
 * @category   Sfynx\CoreBundle\Generator
 * @package    Domain
 * @subpackage TemplaterTemplater_\Architecture\Domain\WorkflowObserver
 *
 * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
 */
class Templater extends AbstractTemplater implements TemplaterInterface
{
    /** @var string */
    const TAG = 'templater_archi_dom_work_obs';

    /** @var array */
    const TARGET_ATTRIBUTS = [
        'conf-mapping' => 'commandFields',
        'conf-widget',
        'conf-cqrs'
    ];

    /** @var string */
    const TEMPLATE_GENERATOR = ReporterObservable::GENERATOR_PHP_MULTIPLE;

    /**
     * @inheritdoc
     */
    public static function scriptList(string $template): array
    {
        return ['Domain\Worflow\Observer'];
    }

    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return 'Service class';
    }

    /**
     * @inheritdoc
     */
    public function getCategory(): string
    {
        return WidgetInterface::CAT_ARCHI_DOM;
    }

    /**
     * @inheritdoc
     */
    public function getTag(): string
    {
        return self::TAG;
    }

    /**
     * @inheritdoc
     */
    public function getDescription(): string
    {
        return <<<EOT
This class expose workflow observer component
EOT;
    }

    /**
     * @inheritdoc
     */
    public function getClassValue(array $data = []): string
    {
        print_r($data);

//        $namespace = new Nette\PhpGenerator\PhpNamespace('Foo');
//        $namespace->addUse('Bar\AliasedClass');
//
//        $class = $namespace->addClass('Demo');
//        $class->addImplement('Foo\A') // resolves to A
//        ->addTrait('Bar\AliasedClass'); // resolves to AliasedClass
//
//        $method = $class->addMethod('method');
//        $method->addParameter('arg')
//            ->setTypeHint('Bar\OtherClass'); // resolves to \Bar\OtherClass
//
        return '';
    }
}

