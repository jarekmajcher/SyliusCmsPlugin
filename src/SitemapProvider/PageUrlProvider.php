<?php

/*
 * This file has been created by developers from BitBag.
 * Feel free to contact us once you face any issues or want to start
 * another great project.
 * You can find more information about us on https://bitbag.shop and write us
 * an email on mikolaj.krol@bitbag.pl.
 */

declare(strict_types=1);

namespace BitBag\SyliusCmsPlugin\SitemapProvider;

use BitBag\SyliusCmsPlugin\Entity\PageInterface;
use BitBag\SyliusCmsPlugin\Entity\PageTranslationInterface;
use BitBag\SyliusCmsPlugin\Repository\PageRepositoryInterface;
use Doctrine\Common\Collections\Collection;
use SitemapPlugin\Factory\AlternativeUrlFactoryInterface;
use SitemapPlugin\Factory\SitemapUrlFactoryInterface;
use SitemapPlugin\Factory\UrlFactoryInterface;
use SitemapPlugin\Model\ChangeFrequency;
use SitemapPlugin\Model\UrlInterface;
use SitemapPlugin\Provider\UrlProviderInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Component\Resource\Model\TranslationInterface;
use Symfony\Component\Routing\RouterInterface;

final class PageUrlProvider implements UrlProviderInterface
{
    /** @var PageRepositoryInterface */
    private $pageRepository;

    /** @var RouterInterface */
    private $router;

    /** @var UrlFactoryInterface */
    private $urlFactory;

    /** @var LocaleContextInterface */
    private $localeContext;

    /** @var ChannelContextInterface */
    private $channelContext;

    public function __construct(
        PageRepositoryInterface $pageRepository,
        RouterInterface $router,
        UrlFactoryInterface $urlFactory,
        LocaleContextInterface $localeContext,
        ChannelContextInterface $channelContext
    ) {
        $this->pageRepository = $pageRepository;
        $this->router = $router;
        $this->urlFactory = $urlFactory;
        $this->localeContext = $localeContext;
        $this->channelContext = $channelContext;
    }

    public function getName(): string
    {
        return 'cms_pages';
    }

    public function generate(ChannelInterface $channel): iterable
    {
        $urls = [];

        foreach ($this->getPages() as $page) {
            $urls[] = $this->createPageUrl($page);
        }

        return $urls;
    }

    private function getTranslations(PageInterface $page): Collection
    {
        return $page->getTranslations()->filter(function (TranslationInterface $translation) {
            return $this->localeInLocaleCodes($translation);
        });
    }

    private function localeInLocaleCodes(TranslationInterface $translation): bool
    {
        return in_array($translation->getLocale(), $this->getLocaleCodes());
    }

    private function getPages(): iterable
    {
        return $this->pageRepository->findEnabled(true);
    }

    private function getLocaleCodes(): array
    {
        /** @var ChannelInterface $channel */
        $channel = $this->channelContext->getChannel();

        return $channel->getLocales()->map(function (LocaleInterface $locale) {
            return $locale->getCode();
        })->toArray();
    }

    private function createPageUrl(PageInterface $page): UrlInterface
    {
        $pageUrl = $this->urlFactory->createNew('');

        $pageUrl->setChangeFrequency(ChangeFrequency::daily());
        $pageUrl->setPriority(0.7);

        if ($page->getUpdatedAt()) {
            $pageUrl->setLastModification($page->getUpdatedAt());
        } elseif ($page->getCreatedAt()) {
            $pageUrl->setLastModification($page->getCreatedAt());
        }

        /** @var PageTranslationInterface $translation */
        foreach ($this->getTranslations($page) as $translation) {
            if (!$translation->getLocale() || !$this->localeInLocaleCodes($translation)) {
                continue;
            }

            $location = $this->router->generate('bitbag_sylius_cms_plugin_shop_page_show', [
                'slug' => $translation->getSlug(),
                '_locale' => $translation->getLocale(),
            ]);

            if ($translation->getLocale() === $this->localeContext->getLocaleCode()) {
                $pageUrl->setLocation($location);

                continue;
            }

            $pageUrl->addAlternative($location, $translation->getLocale());
        }

        return $pageUrl;
    }
}
