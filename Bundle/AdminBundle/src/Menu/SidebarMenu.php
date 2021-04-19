<?php

namespace Umbrella\AdminBundle\Menu;

use Symfony\Component\Yaml\Yaml;
use Twig\Environment;
use Umbrella\CoreBundle\Component\Menu\MenuBuilder;
use Umbrella\CoreBundle\Component\Menu\Model\Breadcrumb;
use Umbrella\CoreBundle\Component\Menu\Model\Menu;

/**
 * Class SidebarMenu.
 */
class SidebarMenu
{
    protected Environment $twig;

    private string $ymlPath;
    private string $theme;

    /**
     * SidebarMenu constructor.
     */
    public function __construct(Environment $twig, string $ymlPath, string $theme)
    {
        $this->twig = $twig;
        $this->ymlPath = $ymlPath;
        $this->theme = $theme;
    }

    public function createMenu(MenuBuilder $builder): Menu
    {
        if (!file_exists($this->ymlPath)) {
            throw new \LogicException(sprintf("Can't load menu from YAML, resource %s doesn't exist", $this->ymlPath));
        }

        $data = (array) Yaml::parse(file_get_contents($this->ymlPath));

        $root = $builder->root();
        foreach ($data as $id => $childOptions) {
            $root->add($id)->configure($childOptions);
        }

        return $builder->getMenu();
    }

    public function renderMenu(Menu $menu): string
    {
        return $this->twig->render('@UmbrellaAdmin/Menu/sidebar.html.twig', [
            'menu' => $menu,
            'theme' => $this->theme
        ]);
    }

    public function renderBreadcrumb(Breadcrumb $breadcrumb): string
    {
        return $this->twig->render('@UmbrellaAdmin/Menu/breadcrumb.html.twig', [
            'breadcrumb' => $breadcrumb,
        ]);
    }
}
