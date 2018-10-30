<?php

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Tests\Command;

use Contao\CoreBundle\Command\SymlinksCommand;
use Contao\CoreBundle\Config\ResourceFinder;
use Contao\CoreBundle\Tests\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\LockHandler;

/**
 * Tests the SymlinksCommand class.
 *
 * @author Leo Feyer <https://github.com/leofeyer>
 */
class SymlinksCommandTest extends TestCase
{
    /**
     * {@inheritdoc}
     */
    public function tearDown()
    {
        $fs = new Filesystem();

        $fs->remove($this->getRootDir().'/system/logs');
        $fs->remove($this->getRootDir().'/system/themes');
        $fs->remove($this->getRootDir().'/var');
        $fs->remove($this->getRootDir().'/web');
    }

    /**
     * Tests symlinking the Contao folders.
     */
    public function testSymlinksTheContaoFolders()
    {
        $fs = new Filesystem();
        $fs->mkdir($this->getRootDir().'/var/logs');

        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', $this->getRootDir());

        $command = new SymlinksCommand(
            $this->getRootDir(),
            'files',
            $this->getRootDir().'/var/logs',
            new ResourceFinder($this->getRootDir().'/vendor/contao/test-bundle/Resources/contao')
        );

        $command->setContainer($container);

        $tester = new CommandTester($command);
        $code = $tester->execute([]);
        $display = $tester->getDisplay();

        $this->assertSame(0, $code);
        $this->assertContains('web/system/modules/foobar/assets', $display);
        $this->assertContains('system/modules/foobar/assets', $display);
        $this->assertContains('web/system/modules/foobar/html', $display);
        $this->assertContains('system/modules/foobar/html', $display);
        $this->assertContains('web/system/modules/foobar/html/foo', $display);
        $this->assertContains('Skipped because system/modules/foobar/html will be symlinked.', $display);
        $this->assertContains('system/themes/flexible', $display);
        $this->assertContains('vendor/contao/test-bundle/Resources/contao/themes/flexible', $display);
        $this->assertContains('web/assets', $display);
        $this->assertContains('assets', $display);
        $this->assertContains('web/system/themes', $display);
        $this->assertContains('system/themes', $display);
        $this->assertContains('system/logs', $display);
        $this->assertContains('var/logs', $display);
    }

    /**
     * Tests that the command is locked while running.
     */
    public function testIsLockedWhileRunning()
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', 'foobar');

        $lock = new LockHandler('contao:symlinks', sys_get_temp_dir().'/'.md5('foobar'));
        $lock->lock();

        $command = new SymlinksCommand(
            $this->getRootDir(),
            'files',
            $this->getRootDir().'/var/logs',
            new ResourceFinder($this->getRootDir().'/vendor/contao/test-bundle/Resources/contao')
        );

        $command->setContainer($container);

        $tester = new CommandTester($command);

        $code = $tester->execute([]);

        $this->assertSame(1, $code);
        $this->assertContains('The command is already running in another process.', $tester->getDisplay());

        $lock->release();
    }

    /**
     * Tests that absolute paths are converted to relative paths.
     */
    public function testConvertsAbsolutePathsToRelativePaths()
    {
        $command = new SymlinksCommand(
            $this->getRootDir(),
            'files',
            $this->getRootDir().'/var/logs',
            new ResourceFinder($this->getRootDir().'/vendor/contao/test-bundle/Resources/contao')
        );

        // Use \ as directory separator in $rootDir
        $rootDir = new \ReflectionProperty(SymlinksCommand::class, 'rootDir');
        $rootDir->setAccessible(true);
        $rootDir->setValue($command, strtr($this->getRootDir(), '/', '\\'));

        // Use / as directory separator in $path
        $method = new \ReflectionMethod(SymlinksCommand::class, 'getRelativePath');
        $method->setAccessible(true);
        $relativePath = $method->invoke($command, $this->getRootDir().'/var/logs');

        // The path should be normalized and shortened
        $this->assertSame('var/logs', $relativePath);
    }
}
