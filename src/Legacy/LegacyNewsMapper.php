<?php

declare(strict_types=1);

namespace TVSumare\Legacy;

final class LegacyNewsMapper
{
    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    public function map(array $item): array
    {
        return [
            'title' => $this->first($item, ['title', 'titulo', 'headline']),
            'summary' => $this->first($item, ['summary', 'resumo', 'linha_fina', 'description']),
            'body' => $this->first($item, ['body', 'texto', 'content', 'conteudo']),
            'city' => $this->first($item, ['city', 'cidade', 'municipio']),
            'category' => $this->normalizeCategory($this->first($item, ['category', 'categoria', 'editoria'])),
            'url' => $this->first($item, ['url', 'link', 'source_url', 'origem_url']),
            'source' => $this->first($item, ['source', 'fonte', 'origem']),
            'published_at' => $this->first($item, ['published_at', 'data', 'created_at', 'pubDate']),
            'og_image' => $this->first($item, ['og_image', 'imagem_og']),
            'rss_image' => $this->first($item, ['rss_image', 'imagem_rss']),
            'source_image' => $this->first($item, ['source_image', 'imagem_fonte']),
            'body_image' => $this->first($item, ['body_image', 'imagem', 'image', 'foto']),
            'category_image' => $this->categoryImage($this->first($item, ['category', 'categoria', 'editoria'])),
            'legacy' => $item,
        ];
    }

    /** @param string[] $keys */
    private function first(array $item, array $keys): string
    {
        foreach ($keys as $key) {
            if (isset($item[$key]) && trim((string)$item[$key]) !== '') {
                return trim((string)$item[$key]);
            }
        }

        return '';
    }

    private function normalizeCategory(string $category): string
    {
        $category = trim($category);
        return $category !== '' ? mb_convert_case($category, MB_CASE_TITLE, 'UTF-8') : 'Geral';
    }

    private function categoryImage(string $category): string
    {
        $slug = mb_strtolower(trim($category));
        return match (true) {
            str_contains($slug, 'emprego') => '/assets/img/category-empregos.jpg',
            str_contains($slug, 'saúde'), str_contains($slug, 'saude') => '/assets/img/category-saude.jpg',
            str_contains($slug, 'segurança'), str_contains($slug, 'seguranca') => '/assets/img/category-seguranca.jpg',
            str_contains($slug, 'educa') => '/assets/img/category-educacao.jpg',
            str_contains($slug, 'econom') => '/assets/img/category-economia.jpg',
            default => '/assets/img/tvsumare-default-news.jpg',
        };
    }
}
