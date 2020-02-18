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

use BitBag\SyliusCmsPlugin\Entity\SectionInterface;
use BitBag\SyliusCmsPlugin\Entity\SectionTranslationInterface;
use BitBag\SyliusCmsPlugin\Repository\SectionRepositoryInterface;
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

final class SectionUrlProvider implements UrlProviderInterface
{
    /** @var SectionRepositoryInterface */
    private $sectionRepository;

    /** @var RouterInterface */
    private $router;

    /** @var UrlFactoryInterface */
    private $urlFactory;

    /** @var LocaleContextInterface */
    private $localeContext;

    /** @var ChannelContextInterface */
    private $channelContext;

    public function __construct(
        SectionRepositoryInterface $sectionRepository,
        RouterInterface $router,
        UrlFactoryInterface $urlFactory,
        LocaleContextInterface $localeContext,
        ChannelContextInterface $channelContext
    ) {
        $this->sectionRepository = $sectionRepository;
        $this->router = $router;
        $this->urlFactory = $urlFactory;
        $this->localeContext = $localeContext;
        $this->channelContext = $channelContext;
    }

    public function getName(): string
    {
        return 'cms_sections';
    }

    public function generate(ChannelInterface $channel): iterable
    {
        $urls = [];

        foreach ($this->getSections() as $section) {
            $urls[] = $this->createSectionUrl($section);
        }

        return $urls;
    }

    private function getTranslations(SectionInterface $section): Collection
    {
        return $section->getTranslations()->filter(function (TranslationInterface $translation) {
            return $this->localeInLocaleCodes($translation);
        });
    }

    private function localeInLocaleCodes(TranslationInterface $translation): bool
    {
        return in_array($translation->getLocale(), $this->getLocaleCodes());
    }

    private function getSections(): iterable
    {
        return $this->sectionRepository->findAll();
    }

    private function getLocaleCodes(): array
    {
        /** @var ChannelInterface $channel */
        $channel = $this->channelContext->getChannel();

        return $channel->getLocales()->map(function (LocaleInterface $locale) {
            return $locale->getCode();
        })->toArray();
    }

    private function createSectionUrl(SectionInterface $section): UrlInterface
    {
        $url = $this->urlFactory->createNew('');

        $url->setChangeFrequency(ChangeFrequency::daily());
        $url->setPriority(0.7);

        /** @var SectionTranslationInterface $translation */
        foreach ($this->getTranslations($section) as $translation) {
            if (!$translation->getLocale() || !$this->localeInLocaleCodes($translation)) {
                continue;
            }

            $location = $this->router->generate('bitbag_sylius_cms_plugin_shop_section_show', [
                'code' => $section->getCode(),
                '_locale' => $translation->getLocale(),
            ]);

            if ($translation->getLocale() === $this->localeContext->getLocaleCode()) {
                $url->setLocation($location);

                continue;
            }

            $url->addAlternative($location, $translation->getLocale());
        }

        return $url;
    }
}
