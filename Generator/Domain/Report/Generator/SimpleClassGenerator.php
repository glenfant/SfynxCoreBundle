<?php
namespace Sfynx\CoreBundle\Generator\Domain\Report\Generator;

use Sfynx\CoreBundle\Generator\Domain\Templater\Generalisation\Interfaces\TemplaterInterface;
use Sfynx\CoreBundle\Generator\Domain\Report\Generalisation\AbstractGenerator;
use Sfynx\CoreBundle\Generator\Domain\Component\File\ClassHandler;

/**
 * Class SimpleClassGenerator
 *
 * @category   Sfynx\CoreBundle\Generator
 * @package    Domain
 * @subpackage Report\Generator
 *
 * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
 */
class SimpleClassGenerator extends AbstractGenerator
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

        foreach ($templater::scriptList(strtolower($templater->template)) as $tagTemplater => $data) {
            list($namespace, $source) = $data;
            list($direname, $basename, $extension, $templater->targetClassname) = array_values(pathinfo($source));

            if ($templater->has('targetCqrs')) {
                $namespace = $namespace . '\\' . $templater->getTargetCqrs();
            }

            if ($templater->has('targetClass')) {
                $templater->targetClassname = $templater->getTargetClass();

                if (strrpos($templater->targetClassname, '\\')) {
                    $namespace = $namespace . '\\' . ClassHandler::getDirenameFromNamespace($templater->targetClassname);
                    $templater->targetClassname = ClassHandler::getClassNameFromNamespace($templater->targetClassname);
                }
            }

            $templater->targetNamespace = sprintf('%s\%s', $templater->namespace, $namespace);
            $templater->targetPath = sprintf('%s/%s/%s', $templater->reportDir, $namespace, $templater->targetClassname . '.' . $extension);

            $templater->targetNamespace = str_replace('\\\\', '\\', $templater->targetNamespace);
            $templater->targetPath = str_replace('\\', '/', $templater->targetPath);

            static::$dataArr[$cat][$tag]['files'][] = [
                'target_namespace' => $templater->getTargetNamespace(),
                'target_path' => $templater->getTargetPath(),
                'target_source' => $this->renderSource($templater, $source),
            ];
        }
    }

    /**
     * @param TemplaterInterface $templater
     * @param string $source
     * @return string
     * @access private
     */
    protected function renderSource(TemplaterInterface $templater, string $source): string
    {
        ob_start();
        require $source;
        $content =  ob_get_clean();

//        $content = \Nette\Utils\Strings::indent(ltrim(rtrim($content) . "\n"), 0);
        $content = \Nette\Utils\Strings::normalize($content);
        $content = \Nette\PhpGenerator\Helpers::tabsToSpaces($content, $templater->getIndentation()) . PHP_EOL;

        return $content;
    }
}
