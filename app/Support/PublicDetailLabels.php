<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Presentation labels for the structured public detail blocks.
 *
 * Block data keys are an API/editorial contract and must never be rendered as
 * user-facing labels. Keeping the mapping here gives every detail block the
 * same translation boundary and makes adding a field an explicit, reviewable
 * change in both the resolver and language catalogues.
 */
final class PublicDetailLabels
{
    /** @var array<string, string> */
    private const FIELD_KEYS = [
        'name' => 'detail_field_name',
        'summary' => 'detail_field_summary',
        'description' => 'detail_field_description',
        'website' => 'detail_field_website',
        'director' => 'detail_field_director',
        'director_ref' => 'detail_field_director_ref',
        'role' => 'detail_field_role',
        'bio' => 'detail_field_bio',
        'subtitle' => 'detail_field_subtitle',
        'synopsis' => 'detail_field_synopsis',
        'duration' => 'detail_field_duration',
        'premiere_date' => 'detail_field_premiere_date',
        'performance_date' => 'detail_field_performance_date',
        'performance_time' => 'detail_field_performance_time',
        'venue' => 'detail_field_venue',
        'price_regular' => 'detail_field_price_regular',
        'price_discount' => 'detail_field_price_discount',
        'audience' => 'detail_field_audience',
        'company' => 'detail_field_company',
        'people' => 'detail_field_people',
        'provider' => 'detail_field_provider',
        'video_id' => 'detail_field_video_id',
        'video_url' => 'detail_field_video_url',
        'recorded_at' => 'detail_field_recorded_at',
        'credit' => 'detail_field_credit',
        'related_works' => 'detail_field_related_works',
        'edition' => 'detail_field_edition',
        'start_date' => 'detail_field_start_date',
        'end_date' => 'detail_field_end_date',
        'status' => 'detail_field_status',
        'works' => 'detail_field_works',
        'videos' => 'detail_field_videos',
        'author' => 'detail_field_author',
        'curator' => 'detail_field_curator',
        'opening_date' => 'detail_field_opening_date',
        'closing_date' => 'detail_field_closing_date',
        'category' => 'detail_field_category',
        'modality' => 'detail_field_modality',
        'schedule' => 'detail_field_schedule',
        'days' => 'detail_field_days',
        'capacity' => 'detail_field_capacity',
        'price' => 'detail_field_price',
        'enrollment_fee' => 'detail_field_enrollment_fee',
        'requirements' => 'detail_field_requirements',
        'objectives' => 'detail_field_objectives',
        'history' => 'detail_field_history',
        'instructors' => 'detail_field_instructors',
        'registration_url' => 'detail_field_registration_url',
        'contact_email' => 'detail_field_contact_email',
        'publication_type' => 'detail_field_publication_type',
        'authors' => 'detail_field_authors',
        'publication_date' => 'detail_field_publication_date',
        'publisher' => 'detail_field_publisher',
        'document_link' => 'detail_field_document_link',
    ];

    /** @var array<string, string> */
    private const VALUE_KEYS = [
        'provider.youtube' => 'detail_value_provider_youtube',
        'provider.vimeo' => 'detail_value_provider_vimeo',
        'provider.other' => 'detail_value_other',
        'status.upcoming' => 'detail_value_status_upcoming',
        'status.open' => 'detail_value_status_open',
        'status.finished' => 'detail_value_status_finished',
        'status.cancelled' => 'detail_value_status_cancelled',
        'modality.presencial' => 'detail_value_modality_in_person',
        'modality.online' => 'detail_value_modality_online',
        'modality.hibrido' => 'detail_value_modality_hybrid',
        'publication_type.editorial' => 'detail_value_publication_editorial',
        'publication_type.press' => 'detail_value_publication_press',
        'publication_type.transparency' => 'detail_value_publication_transparency',
        'publication_type.other' => 'detail_value_other',
        'relation_type.related' => 'detail_value_relation_related',
        'relation_type.recommended' => 'detail_value_relation_recommended',
        'relation_type.prerequisite' => 'detail_value_relation_prerequisite',
        'relation_type.sequel' => 'detail_value_relation_sequel',
    ];

    public static function field(string $key): string
    {
        $translationKey = self::FIELD_KEYS[$key] ?? null;

        return $translationKey !== null
            ? (string) lang('Site.' . $translationKey)
            : self::fallback($key);
    }

    public static function value(string $field, string $value): string
    {
        $normalizedValue = strtolower(trim($value));
        $translationKey = self::VALUE_KEYS[$field . '.' . $normalizedValue] ?? null;

        return $translationKey !== null
            ? (string) lang('Site.' . $translationKey)
            : $value;
    }

    private static function fallback(string $key): string
    {
        $normalized = preg_replace('/[_-]+/', ' ', trim($key)) ?? trim($key);

        return function_exists('mb_convert_case')
            ? mb_convert_case($normalized, MB_CASE_TITLE, 'UTF-8')
            : ucwords($normalized);
    }
}
