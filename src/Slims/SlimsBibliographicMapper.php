<?php
namespace AssistedAutocataloguing\Slims;

use AssistedAutocataloguing\Metadata\InternalBiblioModel;

/**
 * Maps InternalBiblioModel to SLiMS 9 Bulian Database Schema
 */
class SlimsBibliographicMapper
{
    private MasterDataResolver $resolver;
    private array $defaultConfig;

    public function __construct(MasterDataResolver $resolver, array $defaultConfig = [])
    {
        $this->resolver = $resolver;
        $this->defaultConfig = $defaultConfig;
    }

    /**
     * Map model to SLiMS biblio table array & relational arrays
     */
    public function map(InternalBiblioModel $model, ?int $userId = 1): array
    {
        $fields = $model->getAllFields();
        $unmapped = [];

        $title = trim($fields['title']['selected_value'] ?? ($fields['title']['ocr_value'] ?? ($fields['title']['external_value'] ?? '')));
        if (empty($title)) {
            return [
                'valid' => false,
                'error' => 'Judul buku (Title) wajib diisi sebelum menyimpan ke SLiMS.',
                'data' => null,
            ];
        }

        // Statement of responsibility (SOR)
        $sor = trim($fields['sor']['selected_value'] ?? ($fields['sor']['ocr_value'] ?? ($fields['sor']['external_value'] ?? '')));

        // Edition
        $edition = trim($fields['edition']['selected_value'] ?? ($fields['edition']['ocr_value'] ?? ($fields['edition']['external_value'] ?? '')));

        // ISBN
        $isbn = trim($fields['isbn']['selected_value'] ?? ($fields['isbn']['ocr_value'] ?? ($fields['isbn']['external_value'] ?? '')));

        // Year
        $year = trim($fields['publish_year']['selected_value'] ?? ($fields['publish_year']['ocr_value'] ?? ($fields['publish_year']['external_value'] ?? '')));

        // Collation
        $collation = trim($fields['collation']['selected_value'] ?? ($fields['collation']['ocr_value'] ?? ($fields['collation']['external_value'] ?? '')));

        // Series Title
        $seriesTitle = trim($fields['series_title']['selected_value'] ?? ($fields['series_title']['ocr_value'] ?? ($fields['series_title']['external_value'] ?? '')));

        // Classification
        $classification = trim($fields['classification']['selected_value'] ?? ($fields['classification']['ocr_value'] ?? ($fields['classification']['external_value'] ?? '')));

        // Publisher & Place resolution
        $pubName = trim($fields['publisher']['selected_value'] ?? ($fields['publisher']['ocr_value'] ?? ($fields['publisher']['external_value'] ?? '')));
        $publisherId = $this->resolver->resolvePublisher($pubName);

        $placeName = trim($fields['publish_place']['selected_value'] ?? ($fields['publish_place']['ocr_value'] ?? ($fields['publish_place']['external_value'] ?? '')));
        $placeId = $this->resolver->resolvePlace($placeName);

        // Authors resolution
        $authorsRaw = $fields['authors']['selected_value'] ?? ($fields['authors']['ocr_value'] ?? ($fields['authors']['external_value'] ?? []));
        $authorRelations = [];
        $firstAuthorName = '';

        if (is_array($authorsRaw)) {
            $level = 1;
            foreach ($authorsRaw as $auth) {
                $aName = is_array($auth) ? ($auth['name'] ?? '') : (string)$auth;
                $aName = trim($aName);
                if (!empty($aName)) {
                    if (empty($firstAuthorName)) {
                        $firstAuthorName = $aName;
                    }
                    $authorId = $this->resolver->resolveAuthor($aName);
                    if ($authorId) {
                        $authorRelations[] = [
                            'author_id' => $authorId,
                            'level' => $level,
                            'name' => $aName,
                        ];
                        $level = 2; // subsequent authors are additional
                    }
                }
            }
        }

        // Subjects resolution
        $subjectsRaw = $fields['subjects']['selected_value'] ?? ($fields['subjects']['ocr_value'] ?? ($fields['subjects']['external_value'] ?? []));
        $topicRelations = [];
        if (is_array($subjectsRaw)) {
            $level = 1;
            foreach ($subjectsRaw as $sub) {
                $sName = trim((string)$sub);
                if (!empty($sName)) {
                    $topicId = $this->resolver->resolveTopic($sName);
                    if ($topicId) {
                        $topicRelations[] = [
                            'topic_id' => $topicId,
                            'level' => $level++,
                            'topic' => $sName,
                        ];
                    }
                }
            }
        }

        // Call Number Auto-Generation (Standard library convention or custom template)
        $callNumber = trim($fields['call_number']['selected_value'] ?? ($fields['call_number']['ocr_value'] ?? ($fields['call_number']['external_value'] ?? '')));
        if (empty($callNumber) && !empty($classification)) {
            $callNumber = $this->generateCallNumber([
                'classification' => $classification,
                'author' => $firstAuthorName,
                'title' => $title,
                'year' => $year,
                'edition' => $edition,
                'publisher' => $pubName,
            ]);
        }

        // Notes
        $notes = trim($fields['notes']['selected_value'] ?? ($fields['notes']['ocr_value'] ?? ($fields['notes']['external_value'] ?? '')));

        // Image / Cover
        $image = trim($fields['image']['selected_value'] ?? ($fields['image']['ocr_value'] ?? ($fields['image']['external_value'] ?? '')));
        if (empty($image) && !empty($_SESSION['aac_state']['cover_image'])) {
            $image = $_SESSION['aac_state']['cover_image'];
        }

        // Language & GMD
        $gmdId = $this->defaultConfig['default_gmd_id'] ?? 1;
        $languageId = $this->defaultConfig['default_language_id'] ?? 'id';

        $biblioData = [
            'gmd_id' => $gmdId,
            'title' => $title,
            'sor' => $sor,
            'edition' => $edition,
            'isbn_issn' => $isbn,
            'publisher_id' => $publisherId,
            'publish_year' => $year,
            'collation' => $collation,
            'series_title' => $seriesTitle,
            'call_number' => $callNumber,
            'language_id' => $languageId,
            'source' => 'ai_aac',
            'publish_place_id' => $placeId,
            'classification' => $classification,
            'notes' => $notes,
            'image' => !empty($image) ? $image : null,
            'file_att' => null,
            'opac_hide' => 0,
            'promoted' => 0,
            'labels' => null,
            'frequency_id' => 0,
            'spec_detail_info' => null,
            'content_type_id' => null,
            'media_type_id' => null,
            'carrier_type_id' => null,
            'input_date' => date('Y-m-d H:i:s'),
            'last_update' => date('Y-m-d H:i:s'),
            'uid' => $userId ?: 1,
        ];

        return [
            'valid' => true,
            'error' => null,
            'biblio' => $biblioData,
            'authors' => $authorRelations,
            'topics' => $topicRelations,
            'unmapped' => $unmapped,
        ];
    }

    /**
     * Generate call number based on configurable template and bibliographical components
     */
    public function generateCallNumber(array $data, ?string $template = null): string
    {
        $tmpl = $template ?: ($this->defaultConfig['call_number_template'] ?? '{class} {author} {title}');

        $class = trim((string)($data['classification'] ?? ''));
        $firstAuthor = trim((string)($data['author'] ?? ''));
        $title = trim((string)($data['title'] ?? ''));
        $year = trim((string)($data['year'] ?? ''));
        $edition = trim((string)($data['edition'] ?? ''));
        $publisher = trim((string)($data['publisher'] ?? ''));
        $copy = trim((string)($data['copy'] ?? 'c.1'));
        $itemCode = trim((string)($data['item_code'] ?? ''));

        // Standard Perpusnas author code: 3 uppercase letters of author name
        $authorCode = !empty($firstAuthor) ? strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $firstAuthor), 0, 3)) : 'XXX';
        // Standard Perpusnas title code: 1 lowercase letter of main title
        $titleCode = !empty($title) ? strtolower(substr(preg_replace('/[^a-zA-Z]/', '', $title), 0, 1)) : '';

        $replacements = [
            '{class}' => $class,
            '{author}' => $authorCode,
            '{author_code}' => $authorCode,
            '{title}' => $titleCode,
            '{title_code}' => $titleCode,
            '{year}' => $year,
            '{edition}' => $edition,
            '{publisher}' => !empty($publisher) ? strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $publisher), 0, 4)) : '',
            '{copy}' => $copy,
            '{copies}' => $copy,
            '{item_code}' => $itemCode,
        ];

        $result = str_ireplace(array_keys($replacements), array_values($replacements), $tmpl);
        $result = trim(preg_replace('/\s+/', ' ', $result));
        return $result;
    }
}
