<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function getProjectDir(): string
    {
        return \dirname(__DIR__, 2);
    }

    public function getCacheDir(): string
    {
        return $this->getProjectDir().'/slw-content/cache/'.$this->environment;
    }

    public function getLogDir(): string
    {
        return $this->getProjectDir().'/slw-content/log';
    }

    public function registerBundles(): iterable
    {
        $bundlesPath = $this->getProjectDir().'/app/config/bundles.php';
        if (!is_file($bundlesPath)) {
            yield new FrameworkBundle();

            return;
        }

        foreach (require $bundlesPath as $class => $envs) {
            if ($envs[$this->environment] ?? $envs['all'] ?? false) {
                yield new $class();
            }
        }
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $configDir = $this->getProjectDir().'/app/config';
        $configDirGlob = preg_replace('{/config$}', '/{config}', $configDir);

        $container->import($configDirGlob.'/{packages}/*.{php,yaml}');
        $container->import($configDirGlob.'/{packages}/'.$this->environment.'/*.{php,yaml}');

        if (is_file($configDir.'/services.yaml')) {
            $container->import($configDirGlob.'/services.yaml');
            $container->import($configDirGlob.'/{services}_'.$this->environment.'.yaml');
        } else {
            $container->import($configDirGlob.'/{services}.php');
            $container->import($configDirGlob.'/{services}_'.$this->environment.'.php');
        }
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $configDir = $this->getProjectDir().'/app/config';
        $configDirGlob = preg_replace('{/config$}', '/{config}', $configDir);

        $routes->import($configDirGlob.'/{routes}/'.$this->environment.'/*.{php,yaml}');
        $routes->import($configDirGlob.'/{routes}/*.{php,yaml}');

        if (is_file($configDir.'/routes.yaml')) {
            $routes->import($configDirGlob.'/routes.yaml');
        } else {
            $routes->import($configDirGlob.'/{routes}.php');
        }

        if ($fileName = (new \ReflectionObject($this))->getFileName()) {
            $routes->import($fileName, 'attribute');
        }
    }

    /**
     * @return list<string> An array of allowed values for APP_ENV
     */
    private function getAllowedEnvs(): array
    {
        return ['prod', 'dev', 'test'];
    }
}
