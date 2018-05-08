<?php
namespace Sfynx\CoreBundle\Generator\Domain\Report\Generator;

use Sfynx\CoreBundle\Generator\Domain\Templater\Generalisation\Interfaces\TemplaterInterface;
use Sfynx\CoreBundle\Generator\Domain\Report\Generalisation\AbstractGenerator;

/**
 * Class PhpClassesGenerator
 *
 * @category   Sfynx\CoreBundle\Generator
 * @package    Domain
 * @subpackage Report\Generator
 *
 * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
 */
class PhpClassesGenerator extends AbstractGenerator
{
    /**
     * @inheritdoc
     */
    public function generateClassData(TemplaterInterface $templater): void
    {
        $tag = $templater->getTag();
        $cat = $templater->getCategory();

        static::$dataArr[$cat][$tag]['name'] = $templater->getName();
        static::$dataArr[$cat][$tag]['desc'] = $templater->getDescription();

        foreach (array_values($templater->getTargetWidget()) as $data) {
            $keys = array_keys($data);
            $values = array_values($data);
            $index = end($keys);
            $config = end($values);

            $templater->targetClassname = $config['class'];

            $namespace = substr($templater->targetClassname, 0, -strlen($templater->targetClassname) + strrpos($templater->targetClassname, '\\'));
            $templater->targetClassname = substr($templater->targetClassname, strrpos($templater->targetClassname, '\\') + 1);

            $templater->targetNamespace = sprintf('%s\%s', $templater->namespace, $namespace);
            $templater->targetPath = sprintf('%s/%s/%s', $templater->reportDir, str_replace('\\', '/', $namespace), $templater->targetClassname . '.php');

            $source = '';
            if ($config['create']) {
                $source = $this->renderSource($templater, $config);
            }

            static::$dataArr[$cat][$tag]['files'][] = [
                'target_namespace' => $templater->getTargetNamespace(),
                'target_path' => $templater->getTargetPath(),
                'target_source' => $source,
            ];
        }
    }
    
    /**
     * @param TemplaterInterface $templater
     * @param array $config
     * @return string
     * @access private
     */
    protected function renderSource(TemplaterInterface $templater, array $config = []): string
    {
        ob_start();
        echo $templater->getClassValue($config);
        return ob_get_clean();
    }
}