<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */
//
//  Copyright (c) 2004-2005 Laurent Bedubourg
//
//  This library is free software; you can redistribute it and/or
//  modify it under the terms of the GNU Lesser General Public
//  License as published by the Free Software Foundation; either
//  version 2.1 of the License, or (at your option) any later version.
//
//  This library is distributed in the hope that it will be useful,
//  but WITHOUT ANY WARRANTY; without even the implied warranty of
//  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
//  Lesser General Public License for more details.
//
//  You should have received a copy of the GNU Lesser General Public
//  License along with this library; if not, write to the Free Software
//  Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
//
//  Authors: Laurent Bedubourg <lbedubourg@motion-twin.com>
//

namespace Sfynx\CoreBundle\Generator\Domain\Component\Xmi;

use Sfynx\CoreBundle\Generator\Domain\Component\Output\CliOutput;

if (!defined('T_NAMESPACE'))
{
    /**
     * This is just for backword compatibilty with previous versions
     * Token -1 will never exists but we just want to avoid having undefined
     * constant
     */
    define('T_NAMESPACE', -1);
    define('T_NS_SEPARATOR', -1);
}
if (!defined('T_TRAIT'))
{
    define('T_TRAIT', -1);
}

class Php2Xmi
{
    public static function xmi2php_requireDirectory($path)
    {
        if ($path[\strlen($path) - 1] != '/') $path .= '/';
        $dir = \dir($path);
        while ($entry = $dir->read()) {
            if ($entry[0] == '.')
                continue;
            $rp = $path . $entry;
            if (\is_file($rp) && \substr($entry, -4) == '.php') {
                require_once $rp;
            }
            if (\is_dir($rp)) {
                self::xmi2php_requireDirectory($rp);
            }
        }
        $dir->close();
    }

    public static function php2xmi_main($output, array $argv)
    {
        $files = [];
        $outputFile = '';
        $recusive = false;
        $showPrivates = true;
        $showProtecteds = true;
        $showPublics = true;
        $builtinClasses = \array_merge(\get_declared_classes(), \get_declared_interfaces());

        foreach ($argv as $arg) {
            if ($arg[0] == '-') {
                if (\preg_match('/^(.*?)=(.*?)$/', $arg, $m)) {
                    list(, $name, $value) = $m;
                } else {
                    $name = $arg;
                    $value = '';
                }
                switch ($name) {
                    case '-h':
                    case '--help':
                        $output->writeln(\sprintf('<info>XMI</info> --help'));
                        self::xmi2php_usage();
                        exit(0);
                    case '--strict':
                        \error_reporting(E_ALL | E_STRICT);
                        break;
                    case '--test':
                        $writer = new XmiWriter();
                        $writer->enablePrivate($showPrivates);
                        $writer->enableProtected($showProtecteds);
                        $writer->enablePublic($showPublics);
                        $writer->addClass('XmiWriter');
                        $writer->addClass('XmiInterfaceWriter');
                        $writer->addClass('XmiClassWriter');
                        $writer->write();
                        exit(0);
                    case '--no-private':
                        $showPrivates = false;
                        break;
                    case '--no-protected':
                        $showProtecteds = false;
                        break;
                    case '--no-public':
                        $showPublics = false;
                        break;
                    case '--autoload':
                        $autoloadManager = new autoloadManager();
                        $autoloadManager->addFolder($value);
                        $autoloadManager->register();
                        break;
                    case '--recursive':
                        $recusive = true;
                        break;
                    case '--output':
                        $outputFile = $value;
                        break;
                    default:
                        $output->writeln(sprintf('<error>XMI</error> unknown parameter %s', $name));
                        self::xmi2php_usage();
                        exit(1);
                }
            } else {
                \array_push($files, $arg);
            }
        }

        if (\count($files) == 0) {
            $output->writeln(sprintf('<error>XMI</error> %s is a directory but --recursive not used (count files = 0)', $file));
            self::xmi2php_usage();
            exit(0);
        }
        $nsr = new XmiNameSpaceResolver();
        foreach ($files as $file) {
            if (\is_dir($file)) {
                if ($recusive) {
                    self::xmi2php_requireDirectory($file);
                } else {
                    $output->writeln(sprintf('<error>XMI</error> %s is a directory but --recursive not used', $file));
                    self::xmi2php_usage();
                    exit(1);
                }
            } else {
                require_once $file;
            }
        }
        foreach (\get_included_files() as $file) {
            $nsr->addFile($file);
        }


        $writer = new XmiWriter();
        $writer->enablePrivate($showPrivates);
        $writer->enableProtected($showProtecteds);
        $writer->enablePublic($showPublics);
        $writer->setNSResolver($nsr);
        $allclasses = \array_merge(\get_declared_classes(), \get_declared_interfaces());
        $userclasses = \array_diff($allclasses, $builtinClasses);
        foreach ($userclasses as $className) {
            $writer->addClass($className);
        }
        $result = $writer->write();

        if ($outputFile == '') {
            echo $result;
        } else {
            \file_put_contents($outputFile, $result);
        }
    }

    public static function xmi2php_usage()
    {
        echo <<<EOUSAGE
    
    PHP2XMI 0.1.2 - (c)2005 Motion-Twin
    
        Usage: php2xmi [Options] <PHP FILES AND DIRECTORIES>
    
    Options:
        --autoload=folder           Folder for the autoload
        --no-private                do not output private methods and attributes
        --no-protected              do not output protected methods and attributes
        --no-public                 do not output public methods and attributes
        --strict                    activate E_STRICT error_reporting
        --help                      shows this help
        --recursive                 look for .php files in specified directories
        --output=<output.xmi>       select xmi output file
    
    Examples:
    
        Create an XMI representation of a PHP file content
    
            php2xmi --output=result.xmi MyFile.php
    
        Create an XMI representation of all PHP files residing in
        /home/user/websize/lib and dump them to stdout
    
            php2xmi \
            --autoload=/home/user/webautoloadlib \
            --no-private \
            --recursive \
            /home/user/website/lib

EOUSAGE;
    }
}

class XmiWriter
{
    const ID_MYSTEREO  = 4;
    const ID_DATATYPE  = 5;
    const ID_INTERFACE = 6;
    const ID_START     = 7;

    public function __construct()
    {
        $this->_xmiId = self::ID_START;
        $this->_packages = [];
        $this->_classes = [];
        $this->_classIds = [];
        $this->_types = [];
        $this->_extends = [];
        $this->_implements = [];
        $this->_showPrivate = true;
        $this->_showProtected = true;
        $this->_showPublic = true;
        $this->_buffer = '';
        $this->_nsResolver = null;
    }

    public function writeData()
    {
        $args = \func_get_args();
        $this->_buffer .= \implode('', $args);
    }

    public function enablePrivate($bool)
    {
        $this->_showPrivate = $bool;
    }

    public function enableProtected($bool)
    {
        $this->_showProtected = $bool;
    }


    public function enablePublic($bool)
    {
        $this->_showPublic = $bool;
    }

    public function setNSResolver($nsr)
    {
        $this->_nsResolver = $nsr;
    }

    public function getNSResolver()
    {
        return $this->_nsResolver;
    }

    public function acceptPrivate()
    {
        return $this->_showPrivate;
    }

    public function acceptPublic()
    {
        return $this->_showPublic;
    }


    public function acceptProtected()
    {
        return $this->_showProtected;
    }

    public function addClass($className)
    {
        $this->_classes[$className] = null;
    }

    public function write()
    {
        foreach ($this->_classes as $name => $false) {
            $this->prepareClass(new \ReflectionClass($name));
        }
        $this->writeHead();
        $this->writeDataTypes();
        $this->writePackages();
        $this->writeClasses();
        foreach ($this->_extends as $ext) {
            $this->writeClassExtends($ext);
        }
        foreach ($this->_implements as $ext) {
            $this->writeClassImplements($ext);
        }
        $this->writeFoot();
        return $this->_buffer;
    }

    private function writeClassExtends(XmiClassExtends $ext)
    {
        $this->writeData('<UML:Generalization child="', $this->getTypeId($ext->getChild()),'" visibility="public" xmi.id="',$this->nextXmiId(),'" parent="',$this->getTypeId($ext->getParent()),'" />',"\n");
    }

    private function writeClassImplements(XmiClassImplements $ext)
    {
        $this->writeData('<UML:Generalization child="', $this->getTypeId($ext->getClassName()),'" visibility="public" xmi.id="',$this->nextXmiId(),'" parent="',$this->getTypeId($ext->getInterfaceName()),'" />',"\n");
    }

    public function nextXmiId()
    {
        return ++$this->_xmiId;
    }

    public function addClassExtends(XmiClassExtends $ext)
    {
        $this->_extends[] = $ext;
    }

    public function addClassImplements(XmiClassImplements $ext)
    {
        $this->_implements[] = $ext;
    }

    private function prepareClass(\ReflectionClass $class)
    {
        $this->registerClass($class->getName());
        if ($class->isInterface()) {
            $writer = new XmiInterfaceWriter($this, $class);
        }
        else {
            $writer = new XmiClassWriter($this, $class);
        }
        $writer->write();
        $this->_classes[$class->getName()] = $writer;
    }

    private function writeClasses()
    {
        foreach ($this->_classes as $name => $classWriter) {
            if ($classWriter != null && !$classWriter->isPackaged()) {
                $this->writeData($classWriter->getXmi());
            }
        }
    }

    private function writeHead()
    {
        $this->writeData('<?xml version="1.0" encoding="UTF-8" ?>',"\n");
        $this->writeData('<XMI xmlns:UML="org.omg/standards/UML" verified="false" timestamp="" xmi.version="1.2">', "\n");
        $this->writeData('<XMI.header>',"\n");
        $this->writeData('<XMI.documentation>', "\n");
        $this->writeData('<XMI.exporter>PHP2XMI</XMI.exporter>', "\n");
        $this->writeData('<XMI.exporterVersion>1.0</XMI.exporterVersion>',"\n");
        $this->writeData('<XMI.exporterEncoding>UnicodeUTF8</XMI.exporterEncoding>',"\n");
        $this->writeData('</XMI.documentation>',"\n");
        $this->writeData('<XMI.model xmi.name="php2xmi" />',"\n");
        $this->writeData('<XMI.metamodel xmi.name="UML" href="UML.xml" xmi.version="1.3" />',"\n");
        $this->writeData('</XMI.header>',"\n");
        $this->writeData('<XMI.content>',"\n");
        $this->writeData('<UML:Model>', "\n");
        $this->writeData('<UML:Namespace.ownedElement>',"\n");
        $this->writeData('<UML:Stereotype visibility="public" xmi.id="',self::ID_MYSTEREO,'" name="my-stereotype" />',"\n");
        $this->writeData('<UML:Stereotype visibility="public" xmi.id="',self::ID_DATATYPE,'" name="datatype" />', "\n");
        $this->writeData('<UML:Stereotype visibility="public" xmi.id="',self::ID_INTERFACE,'" name="interface" />', "\n");
    }

    private function writePackages()
    {
        foreach ($this->_packages as $name => $package) {
            $package->write();
        }
    }

    private function writeDataTypes()
    {
        foreach ($this->_types as $type => $id) {
            if (!\array_key_exists($type, $this->_classIds)) {
                $this->writeDataType($id, $type);
            }
        }
    }

    public function getTypeId($type)
    {
        if (!\array_key_exists($type, $this->_types))
            $this->_types[$type] = $this->nextXmiId();
        return $this->_types[$type];
    }

    public function getPackage($name)
    {
        $parts = explode('.', $name);
        $rootName = array_shift($parts);
        if (!\array_key_exists($rootName, $this->_packages)) {
            $this->_packages[$rootName] = new XmiPackage($this,$rootName);
        }
        $package = $this->_packages[$rootName];

        foreach ($parts as $part) {
            if (!$package->hasPackage($part)) {
                $child = new XmiPackage($this, $part);
                $package->addPackage($child);
            }
            $package = $package->getPackage($part);
        }

        return $package;
    }

    private function registerClass($className)
    {
        $id = $this->getTypeId($className);
        $this->_classIds[$className] = $id;
    }

    private function writeDataType($id, $name)
    {
        $this->writeData('<UML:DataType stereotype="',self::ID_DATATYPE,'" visibility="public" xmi.id="',$id,'" name="', \htmlspecialchars($name),'"/>',"\n");
    }

    private function writeFoot()
    {
        $this->writeData('</UML:Namespace.ownedElement>',"\n");
        $this->writeData('</UML:Model>',"\n");
        $this->writeData('</XMI.content>',"\n");
        $this->writeData('</XMI>');
    }

    public static function extractReturnTypeFromComment($comment)
    {
        if (\preg_match('/@return\s+(\S+)/', $comment, $m)) {
            return $m[1];
        }
        return 'void';
    }

    public static function extractParamTypeFromComment($comment, $name)
    {
        if (\preg_match('/@param\s+\$?'.$name.'\s+(\S+)/', $comment, $m)) {
            return $m[1];
        }
        if (\preg_match('/@param\s+(\S+)\s+\$?'.$name.'/', $comment, $m)) {
            return $m[1];
        }
        return 'mixed';
    }

    public static function extractMemberTypeFromComment($comment)
    {
        if (\preg_match('/@(?:type|var)\s+(\S+)/', $comment, $m)) {
            return $m[1];
        }
        return 'mixed';
    }

    public static function extractPackageNameFromComment($comment)
    {
        if (\preg_match('/@package\s+(\S+)/', $comment, $m)) {
            return $m[1];
        }
        return '';
    }

    private $_packages;
    private $_extends;
    private $_implements;
    private $_showPrivate;
    private $_showProtected;
    private $_showPublic;
    private $_buffer;
    private $_classes;
    private $_xmiId;
    private $_classIds;
    private $_types;
    private $_nsResolver;
}

class XmiPackage
{
    private $_packages;
    private $_classes;
    private $_writer;
    private $_name;

    public function __construct(XmiWriter $writer, $name)
    {
        $this->_writer = $writer;
        $this->_name = $name;
        $this->_classes = [];
        $this->_packages = [];
        $this->_parent = null;
    }

    public function getName()
    {
        if ($this->_parent != null) {
            return $this->_parent->getName().'.'.$this->_name;
        }
        return $this->_name;
    }

    public function addPackage(XmiPackage $package)
    {
        $this->_packages[$package->getName()] = $package;
        $package->_parent = $this;
    }

    public function hasPackage($name)
    {
        return \array_key_exists($name, $this->_packages);
    }

    public function getPackage($name)
    {
        return $this->_packages[$name];
    }

    public function addClass(XmiClassWriter $class)
    {
        \array_push($this->_classes, $class);
    }

    public function write()
    {
        $this->writeHead();
        foreach ($this->_packages as $name => $package) {
            $package->write();
        }
        foreach ($this->_classes as $class) {
            $this->_writer->writeData($class->getXmi());
        }
        $this->writeFoot();
    }

    private function writeHead()
    {
        $this->_writer->writeData('<UML:Package visibility="public" xmi.id="',$this->_writer->nextXmiId(),'" name="package.', \htmlspecialchars($this->_name),'">',"\n");
        $this->_writer->writeData('<UML:Namespace.ownedElement>',"\n");
    }

    private function writeFoot()
    {
        $this->_writer->writeData('</UML:Namespace.ownedElement>',"\n");
        $this->_writer->writeData('</UML:Package>',"\n");
    }
}

class XmiClassWriter
{
    protected $_writer;
    protected $_class;
    protected $_xmi;
    protected $_packaged;

    public function __construct(XmiWriter $writer, \ReflectionClass $class)
    {
        $this->_writer = $writer;
        $this->_class = $class;
        $this->_xmi = '';
        $this->_packaged = false;
    }

    public function isPackaged() {
        return $this->_packaged;
    }

    protected function getId()
    {
        return $this->_writer->getTypeId($this->_class->getName());
    }

    protected function writeData()
    {
        $args = \func_get_args();
        $this->_xmi .= \implode('', $args);
    }

    public function write()
    {
        $parentClass = $this->_class->getParentClass();
        if ($parentClass != null) {
            $this->_writer->addClassExtends(new XmiClassExtends($this->_class->getName(), $parentClass->getName()));
        }
        foreach ($this->_class->getInterfaces() as $interface) {
            if ($parentClass == null || !$parentClass->implementsInterface($interface->getName())) {
                $this->_writer->addClassImplements(new XmiClassImplements($this->_class->getName(), $interface->getName()));
            }
        }

        $packageName = XmiWriter::extractPackageNameFromComment($this->_class->getDocComment());
        if ($packageName) {
            $package = $this->_writer->getPackage($packageName);
            $package->addClass($this);
            $this->_packaged = true;
        }

        $this->writeHead();
        $this->writeMembers();
        $this->writeMethods();
        $this->writeFoot();
    }

    public function getXmi()
    {
        return $this->_xmi;
    }

    public function getTypeId($name)
    {
        return $this->_writer->getTypeId($name);
    }

    protected function writeHead()
    {
        $this->writeData('<UML:Class visibility="public" xmi.id="',$this->getId(),'" name="',$this->_class->getName(),'"');
        if ($this->_class->isAbstract()) $this->writeData(' isAbstract="true"');
        $this->writeData('>', "\n");
        $this->writeData('<UML:Classifier.feature>',"\n");
    }

    protected function writeFoot()
    {
        $this->writeData('</UML:Classifier.feature>',"\n");
        $this->writeData('</UML:Class>',"\n");
    }

    protected function getVisibility(\Reflector $o)
    {
        if ($o->isPublic()) return 'public';
        if ($o->isPrivate()) return 'private';
        if ($o->isProtected()) return 'protected';
    }

    protected function nextXmiId()
    {
        return $this->_writer->nextXmiId();
    }

    protected function writeMembers()
    {
        foreach ($this->_class->getProperties() as $prop) {
            $this->writeProperty($prop);
        }
    }

    protected function writeProperty(\ReflectionProperty $property)
    {
        if (!$this->_writer->acceptPrivate() && $property->isPrivate()) return;
        if (!$this->_writer->acceptProtected() && $property->isProtected()) return;
        if (!$this->_writer->acceptPublic() && $property->isPublic()) return;

        // ignore parent properties
        if ($property->getDeclaringClass() != $this->_class) {
            return;
        }

        if (\version_compare(\phpversion(), '5.1.0', '>=')) {
            // only for PHP 5.1.0 implements ReflectionProperty::getDocComment()
            $type = XmiWriter::extractMemberTypeFromComment($property->getDocComment());
        } else  {
            $type = 'mixed'; // damn it
        }

        $type = $this->_writer->getNSResolver()->getFullyQualifiedClassName($this->_class->name, $type);
        $id = $this->getTypeId($type);


        $this->writeData('<UML:Attribute visibility="',$this->getVisibility($property),'" xmi.id="',$this->nextXmiId(),'" value="" type="',$id,'" name="', \htmlspecialchars($property->getName()),'"');
        if ($property->isStatic()) $this->writeData(' ownerScope="classifier"');
        $this->writeData('/>', "\n");
    }

    protected function writeMethods()
    {
        foreach ($this->_class->getMethods() as $method) {
            $this->writeMethod($method);
        }
    }

    protected function writeMethod(\ReflectionMethod $method)
    {
        if (!$this->_writer->acceptPrivate() && $method->isPrivate()) return;
        if (!$this->_writer->acceptProtected() && $method->isProtected()) return;
        if (!$this->_writer->acceptPublic() && $method->isPublic()) return;

        // ignore parent methods
        if ($method->getDeclaringClass() != $this->_class) {
            return;
        }

        $type = XmiWriter::extractReturnTypeFromComment($method->getDocComment());
        $type = $this->_writer->getNSResolver()->getFullyQualifiedClassName($this->_class->name, $type);

        $id = $this->getTypeId($type);
        $this->writeData('<UML:Operation visibility="',$this->getVisibility($method),'" xmi.id="',$this->nextXmiId(),'" type="',$id,'" name="',$method->getName(),'"');
        if ($method->isAbstract()) $this->writeData(' isAbstract="true"');
        if ($method->isStatic()) $this->writeData(' ownerScope="classifier"');
        $this->writeData('>', "\n");

        $this->writeData('<UML:BehavioralFeature.parameter>',"\n");
        foreach ($method->getParameters() as $param) {
            $this->writeMethodParam($method, $param);
        }
        $this->writeData('</UML:BehavioralFeature.parameter>',"\n");

        $this->writeData('</UML:Operation>', "\n");
    }

    protected function writeMethodParam(\ReflectionMethod $method, \ReflectionParameter $param)
    {
        // $param->getDefaultValue() sometimes makes php crash this it is
        // deactivated until PHP reflection is fixed
        // function foo($var=self::CONST_VALUE);
        $default = $param->isOptional() ? $param->getDefaultValue() : '';
        try {
            $paramClass = $param->getClass();
        }
        catch (\ReflectionException $e) {
            // warning ? param class not included
            $paramClass = null;
        }
        if ($paramClass != null) {
            $type = $paramClass->getName();
        } else {
            $type = XmiWriter::extractParamTypeFromComment($method->getDocComment(), $param->getName());
            $type = $this->_writer->getNSResolver()->getFullyQualifiedClassName($this->_class->name, $type);
        }
        $id = $this->getTypeId($type);
        if (\is_array($default)) $default = '[]';
        $this->writeData('<UML:Parameter visibility="public" xmi.id="',$this->nextXmiId(),'" value="', \htmlspecialchars($default),'" type="',$id,'" name="', htmlspecialchars($param->getName()),'"/>', "\n");
    }
}

class XmiInterfaceWriter extends XmiClassWriter
{
    protected function writeHead()
    {
        $this->writeData('<UML:Interface visibility="public" xmi.id="',$this->getId(),'" isAbstract="true" name="', \htmlspecialchars($this->_class->getName()),'">', "\n");
        $this->writeData('<UML:Classifier.feature>',"\n");
    }

    protected function writeFoot()
    {
        $this->writeData('</UML:Classifier.feature>',"\n");
        $this->writeData('</UML:Interface>',"\n");
    }
}

class XmiClassExtends
{
    private $_childName;
    private $_parentName;

    public function __construct($className, $otherClassName)
    {
        $this->_childName = $className;
        $this->_parentName = $otherClassName;
    }

    public function getChild() { return $this->_childName; }
    public function getParent() { return $this->_parentName; }
}

class XmiClassImplements
{
    private $_className;
    private $_interfaceName;
    
    public function __construct($className, $interfaceName)
    {
        $this->_className = $className;
        $this->_interfaceName = $interfaceName;
    }

    public function getClassName() { return $this->_className; }
    public function getInterfaceName() { return $this->_interfaceName; }
}


class XmiNameSpaceResolver
{
    private $classes = [];

    public function addFile($file)
    {
        $tokens = token_get_all(file_get_contents($file));
        $count = count($tokens);

        $classes = [];
        $namespace = null;
        $aliases = [];
        for ($i = 0 ; $i < $count ; $i++)
        {
            $token = $tokens[$i];
            if ($token[0] === T_USE)
            {
                $status = 0;
                $name = '';
                $alias = '';
                do {
                    $token = $tokens[++$i];
                    switch($token[0])
                    {
                        case T_WHITESPACE:
                            break;
                        case T_AS:
                            $status = 1;
                            break;
                        case T_NS_SEPARATOR:
                        case T_STRING:
                            if (0 === $status) {
                                $name .= $token[1];
                            } else {
                                $alias .= $token[1];
                            }
                            break;
                        case ',':
                        case ';':
                            if (!$alias) {
                                $arr = explode('\\', $name);
                                $alias = array_pop($arr);
                            }
                            $aliases[$alias] = $name;
                            $status = 0;
                            $alias = '';
                            $name = '';
                            break;
                    }

                } while($token[0] != ';');
            } elseif($token[0] == T_NAMESPACE) {
                $namespace = '';
                do {
                    $token = $tokens[++$i];
                    switch($token[0])
                    {
                        case T_NS_SEPARATOR:
                        case T_STRING:
                            $namespace .= $token[1];
                    }
                } while($token[0] != ';');
            } elseif($token[0] == T_INTERFACE || $token[0] == T_CLASS) {
                $class = '';
                do {
                    $token = $tokens[++$i];
                    switch($token[0])
                    {
                        case T_WHITESPACE:
                            break;
                        case T_NS_SEPARATOR:
                        case T_STRING:
                            $classes[] = $token[1];
                            break 2;
                    }
                } while($token[0] != ';');
            }
        }
        foreach ($classes as $class)
        {
            if ($namespace) {
                $class = $namespace . '\\' . $class;
            }
            $this->classes[$class] = array('namespace' => $namespace, 'use' => $aliases);
        }
    }

    public function getClassInfo($class)
    {
        return $this->classes[$class];
    }

    public function getFullyQualifiedClassName($context, $classname)
    {
        if (!preg_match('/^(?:boolean|bool|string|str|integer|int|float|array|mixed|callback)$/i', $classname))
        {
            if ('\\' !== $classname[0]) {
                $explode = \explode('\\', $classname, 2);
                if (isset($this->classes[$context]['use'][$explode[0]])) {
                    $classname = $this->classes[$context]['use'][$explode[0]];
                    if (isset($explode[1])) $classname .= '\\' . $explode[1];
                } elseif (isset($this->classes[$context]['namespace'])) {
                    $classname = $this->classes[$context]['namespace'] . '\\' . $classname;
                }
            }
            if ('\\' === $classname[0]) $classname = substr($classname, 1);
        }
        return $classname;
    }
}

/*******************************************************************************************/
/* AUTOLOAD MANAGER CLASS                                                                  */
/*******************************************************************************************/
/**
 * Note : Code is released under the GNU LGPL
 *
 * Please do not change the header of this file
 *
 * This library is free software; you can redistribute it and/or modify it under the terms of the GNU
 * Lesser General Public License as published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * See the GNU Lesser General Public License for more details.
 */

/**
 * File:        autoloadManager.php
 *
 * @author      Al-Fallouji Bashar & Charron Pierrick
 * @version     2.0
 */


/**
 * autoloadManager class
 *
 * Handles the class autoload feature
 *
 * Register the loadClass function: $autoloader->register();
 * Add a folder to process: $autoloader->addFolder('{YOUR_FOLDER_PATH}');
 *
 * Read documentation for more information.
 */
class autoloadManager
{
    /**
     * Constants used by the checkClass method
     * @var Integer
     */
    const CLASS_NOT_FOUND = 0;
    const CLASS_EXISTS    = 1;
    const CLASS_IS_NULL   = 2;

    /**
     * Folders that should be parsed
     * @var Array
     */
    private $_folders = [];

    /**
     * Excluded folders
     * @var Array
     */
    private $_excludedFolders = [];

    /**
     * Classes and their matching filename
     * @var Array
     */
    private $_classes = [];

    /**
     * Scan files matching this regex
     * @var String
     */
    private $_filesRegex = '/\.(inc|php)$/';

    /**
     * Save path (Default is null)
     * @var String
     */
    private $_saveFile = null;

    /**
     * Regenerate the autoload file or not. (default: not)
     * @var bool Defaults to false.
     */
    private $_regenerate = false;

    /**
     * Get the path where autoload files are saved
     *
     * @return String path where autoload files will be saved
     */
    public function getSaveFile()
    {
        return $this->_saveFile;
    }

    /**
     * Set the path where autoload files are saved
     *
     * @param String $path path where autoload files will be saved
     */
    public function setSaveFile($pathToFile)
    {
        $this->_saveFile = $pathToFile;
        if ($this->_saveFile && \file_exists($this->_saveFile)) {
            $this->_classes = include($this->_saveFile);
        }
    }

    /**
     * Set the file regex
     *
     * @param String
     */
    public function setFileRegex($regex)
    {
        $this->_filesRegex = $regex;
    }

    /**
     * Add a new folder to parse
     *
     * @param String $path Root path to process
     */
    public function addFolder($path)
    {
        if ($realpath = \realpath($path) and \is_dir($realpath)) {
            $this->_folders[] = $realpath;
        } else {
            throw new \Exception('Failed to open dir : ' . $path);
        }
    }

    /**
     * Exclude a folder from the parsing
     *
     * @param String $path Folder to exclude
     */
    public function excludeFolder($path)
    {
        if ($realpath = \realpath($path) and \is_dir($realpath)) {
            $this->_excludedFolders[] = $realpath . DIRECTORY_SEPARATOR;
        } else {
            throw new \Exception('Failed to open dir : ' . $path);
        }
    }

    /**
     * Checks if the class has been defined
     *
     * @param String $className Name of the class
     * @return Boolean true if class exists, false otherwise.
     */
    public function classExists($className)
    {
        return self::CLASS_EXISTS === $this->checkClass($className, $this->_classes);
    }

    /**
     * Set the regeneration of the cached autoload files.
     *
     * @param  bool $flag Ture or False to regenerate the cached autoload file.
     * @return void
     */
    public function setRegenerate($flag)
    {
        $this->_regenerate = $flag;
    }

    /**
     * Get the regeneration of the cached autoload files.
     *
     * @return bool
     */
    public function getRegenerate()
    {
        return $this->_regenerate;
    }

    /**
     * Method used by the spl_autoload_register
     *
     * @param String $className Name of the class
     * @param Boolean $regenerate Indicates if the files should be regenerated
     */
    public function loadClass($className)
    {
        // check if the class already exists in the cache file
        $loaded = $this->checkClass($className, $this->_classes);
        if (true === $this->_regenerate || !$loaded) {
            // parse the folders returns the list of all the classes
            // in the application
            $this->refresh();

            // recheck if the class exists again in the reloaded classes
            $loaded = $this->checkClass($className, $this->_classes);
            if (!$loaded) {
                // set it to null to flag that it was not found
                // This behaviour fixes the problem with infinite
                // loop if we have a class_exists() for an inexistant
                // class.
                $this->_classes[$className] = null;
            }
            // write to a single file
            if ($this->getSaveFile()) {
                $this->saveToFile($this->_classes);
            }
        }
    }

    /**
     * checks if a className exists in the class array
     *
     * @param  mixed  $className    the classname to check
     * @param  array  $classes      an array of classes
     * @return int    errorCode     1 if the class exists
     *                              2 if the class exists and is null
     *                              (there have been an attempt done)
     *                              0 if the class does not exist
     */
    private function checkClass($className, array $classes)
    {
        if (\array_key_exists($className, $classes)) {
            $classPath = $classes[$className];
            // return true if the
            if (null === $classPath) {
                return self::CLASS_IS_NULL;
            } elseif (\file_exists($classPath)) {
                require($classes[$className]);
                return self::CLASS_EXISTS;
            }
        }
        return self::CLASS_NOT_FOUND;
    }


    /**
     * Parse every registred folders, regenerate autoload files and update the $_classes
     */
    private function parseFolders()
    {
        $classesArray = [];
        foreach ($this->_folders as $folder) {
            $classesArray = \array_merge($classesArray, $this->parseFolder($folder));
        }
        return $classesArray;
    }

    /**
     * Parse folder and update $_classes array
     *
     * @param String $folder Folder to process
     * @return Array Array containing all the classes found
     */
    private function parseFolder($folder)
    {
        $classes = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($folder));
        foreach ($files as $file) {
            if ($file->isFile() && preg_match($this->_filesRegex, $file->getFilename())) {
                foreach ($this->_excludedFolders as $folder) {
                    $len = strlen($folder);
                    if (0 === strncmp($folder, $file->getPathname(), $len)) {
                        continue 2;
                    }
                }

                if ($classNames = $this->getClassesFromFile($file->getPathname())) {
                    foreach ($classNames as $className) {
                        // Adding class to map
                        $classes[$className] = $file->getPathname();
                    }
                }
            }
        }
        return $classes;
    }

    /**
     * Extract the classname contained inside the php file
     *
     * @param String $file Filename to process
     * @return Array Array of classname(s) and interface(s) found in the file
     */
    private function getClassesFromFile($file)
    {
        $namespace = null;
        $classes = [];
        $tokens = token_get_all(file_get_contents($file));
        $nbtokens = \count($tokens);

        for ($i = 0 ; $i < $nbtokens ; $i++) {
            switch ($tokens[$i][0]) {
                case T_NAMESPACE:
                    $i+=2;
                    while ($tokens[$i][0] === T_STRING || $tokens[$i][0] === T_NS_SEPARATOR)
                    {
                        $namespace .= $tokens[$i++][1];
                    }
                    break;
                case T_INTERFACE:
                case T_CLASS:
                case T_TRAIT:
                    $i+=2;
                    if ($namespace) {
                        if (!empty($tokens[$i][1])) {
                            $classes[] = $namespace . '\\' . $tokens[$i][1];
                        }
                    } else {
                        if (!empty($tokens[$i][1])) {
                            $classes[] = $tokens[$i][1];
                        }
                    }
                    break;
            }
        }

        return $classes;
    }

    /**
     * Generate a file containing an array.
     * File is generated under the _savePath folder.
     *
     * @param Array $classes Contains all the classes found and the corresponding filename (e.g. {$className} => {fileName})
     * @param String $folder Folder to process
     */
    private function saveToFile(array $classes)
    {
        // Write header and comment
        $content  = '<?php ' . PHP_EOL;
        $content .= '/** ' . PHP_EOL;
        $content .= ' * AutoloadManager Script' . PHP_EOL;
        $content .= ' * ' . PHP_EOL;
        $content .= ' * @authors      Al-Fallouji Bashar & Charron Pierrick' . PHP_EOL;
        $content .= ' * ' . PHP_EOL;
        $content .= ' * @description This file was automatically generated at ' . date('Y-m-d [H:i:s]') . PHP_EOL;
        $content .= ' * ' . PHP_EOL;
        $content .= ' */ ' . PHP_EOL;

        // Export array
        $content .= 'return ' . \var_export($classes, true) . ';';
        \file_put_contents($this->getSaveFile(), $content);
    }

    /**
     * Returns previously registered classes
     *
     * @return array the list of registered classes
     */
    public function getRegisteredClasses()
    {
        return $this->_classes;
    }

    /**
     * Refreshes an already generated cache file
     * This solves problems with previously unexistant classes that
     * have been made available after.
     * The optimize functionnality will look at all null values of
     * the available classes and does a new parse. if it founds that
     * there are classes that has been made available, it will update
     * the file.
     *
     * @return bool true if there has been a change to the array, false otherwise
     */
    public function refresh()
    {
        $existantClasses = $this->_classes;
        $nullClasses = \array_filter($existantClasses, array('self','_getNullElements'));
        $newClasses = $this->parseFolders();

        // $newClasses will override $nullClasses if the same key exists
        // this allows new added classes (that were flagged as null) to be
        // added
        $this->_classes = \array_merge($nullClasses, $newClasses);

        return true;
    }

    /**
     * Generate the autoload file
     *
     * @return void
     */
    public function generate()
    {
        if ($this->getSaveFile()) {
            $this->refresh();
            $this->saveToFile($this->_classes);
        }
    }

    /**
     * returns null elements (used in an array filter)
     *
     * @param mixed $element the element to check
     * @return boolean true if element is null, false otherwise
     */
    private function _getNullElements($element)
    {
        return null === $element;
    }

    /**
     * Registers this autoloadManager on the SPL autoload stack.
     */
    public function register()
    {
        \spl_autoload_register([$this, 'loadClass']);
    }

    /**
     * Removes this autoloadManager from the SPL autoload stack.
     */
    public function unregister()
    {
        \spl_autoload_unregister([$this, 'loadClass']);
    }
}