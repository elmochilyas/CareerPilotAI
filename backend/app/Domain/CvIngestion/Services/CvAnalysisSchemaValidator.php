<?php

namespace App\Domain\CvIngestion\Services;

use App\Domain\CvIngestion\Data\CvAnalysisResult;

class CvAnalysisSchemaValidator
{
    private const VALID_LINK_TYPES = ['linkedin', 'github', 'portfolio', 'other'];

    /**
     * @param  array<string, mixed>  $response
     * @return array{valid: bool, result: CvAnalysisResult|null, errors: list<string>}
     */
    public function validate(array $response): array
    {
        $basicInfo = $this->validateBasicInformation($response['basic_information'] ?? []);
        $headline = $this->validateString($response['headline'] ?? null, 255);
        $summary = $this->validateString($response['professional_summary'] ?? null, 5000);
        $links = $this->validateProfessionalLinks($response['professional_links'] ?? []);
        $experiences = $this->validateExperiences($response['experiences'] ?? []);
        $projects = $this->validateProjects($response['projects'] ?? []);
        $education = $this->validateEducation($response['education'] ?? []);
        $certifications = $this->validateCertifications($response['certifications'] ?? []);
        $languages = $this->validateLanguages($response['languages'] ?? []);
        $skills = $this->validateSkills($response['skills'] ?? []);
        $warnings = $this->validateWarnings($response['warnings'] ?? []);

        $schemaVersion = isset($response['schema_version']) && is_string($response['schema_version'])
            ? mb_substr($response['schema_version'], 0, 30)
            : '1.1.0';

        $result = new CvAnalysisResult(
            basicInformation: $basicInfo,
            headline: $headline,
            professionalSummary: $summary,
            professionalLinks: $links,
            experiences: $experiences,
            projects: $projects,
            education: $education,
            certifications: $certifications,
            languages: $languages,
            skills: $skills,
            warnings: $warnings,
            provider: '',
            model: '',
            promptVersion: null,
            latencyMs: null,
            tokensPrompt: null,
            tokensCompletion: null,
            responseId: null,
            schemaVersion: $schemaVersion,
        );

        return ['valid' => true, 'result' => $result, 'errors' => []];
    }

    /**
     * @param  array<string, mixed>  $info
     * @return array<string, mixed>
     */
    private function validateBasicInformation(array $info): array
    {
        if ($info === []) {
            return [];
        }

        return [
            'full_name' => $this->truncate($info['full_name'] ?? null, 255),
            'email' => $this->truncate($info['email'] ?? null, 255),
            'phone' => $this->truncate($info['phone'] ?? null, 50),
            'city' => $this->truncate($info['city'] ?? null, 100),
            'country' => $this->truncate($info['country'] ?? null, 100),
        ];
    }

    /**
     * @param  array<int, mixed>  $links
     * @return list<array<string, mixed>>
     */
    private function validateProfessionalLinks(array $links): array
    {
        $valid = [];

        foreach ($links as $i => $link) {
            if (! is_array($link)) {
                continue;
            }

            $type = $link['type'] ?? null;
            if (! in_array($type, self::VALID_LINK_TYPES, true)) {
                continue;
            }

            $url = isset($link['url']) && is_string($link['url']) ? mb_substr($link['url'], 0, 500) : '';
            if ($url === '') {
                continue;
            }

            $valid[] = [
                'type' => $type,
                'url' => $url,
                'source' => $this->validateSource($link['source'] ?? null),
            ];
        }

        return $valid;
    }

    /**
     * @param  array<int, mixed>  $items
     * @return list<array<string, mixed>>
     */
    private function validateExperiences(array $items): array
    {
        $valid = [];

        foreach ($items as $i => $item) {
            if (! is_array($item)) {
                continue;
            }

            $title = $this->truncate($item['title'] ?? null, 255);
            $org = $this->truncate($item['organization'] ?? null, 255);
            $description = $this->truncate($item['description'] ?? null, 5000);

            if (! $title) {
                continue;
            }

            $valid[] = [
                'title' => $title,
                'organization' => $org,
                'location' => $this->truncate($item['location'] ?? null, 255),
                'start_date' => $this->truncate($item['start_date'] ?? null, 50),
                'end_date' => $this->truncate($item['end_date'] ?? null, 50),
                'is_current' => isset($item['is_current']) && $item['is_current'] === true,
                'description' => $description,
                'technologies' => $this->validateStringArray($item['technologies'] ?? []),
                'source' => $this->validateSource($item['source'] ?? null),
            ];
        }

        return $valid;
    }

    /**
     * @param  array<int, mixed>  $items
     * @return list<array<string, mixed>>
     */
    private function validateProjects(array $items): array
    {
        $valid = [];

        foreach ($items as $i => $item) {
            if (! is_array($item)) {
                continue;
            }

            $name = $this->truncate($item['name'] ?? null, 255);
            if (! $name) {
                continue;
            }

            $valid[] = [
                'name' => $name,
                'role' => $this->truncate($item['role'] ?? null, 255),
                'description' => $this->truncate($item['description'] ?? null, 5000),
                'technologies' => $this->validateStringArray($item['technologies'] ?? []),
                'url' => $this->truncate($item['url'] ?? null, 500),
                'start_date' => $this->truncate($item['start_date'] ?? null, 50),
                'end_date' => $this->truncate($item['end_date'] ?? null, 50),
                'is_current' => isset($item['is_current']) && $item['is_current'] === true,
                'source' => $this->validateSource($item['source'] ?? null),
            ];
        }

        return $valid;
    }

    /**
     * @param  array<int, mixed>  $items
     * @return list<array<string, mixed>>
     */
    private function validateEducation(array $items): array
    {
        $valid = [];

        foreach ($items as $i => $item) {
            if (! is_array($item)) {
                continue;
            }

            $degree = $this->truncate($item['degree'] ?? null, 255);
            $institution = $this->truncate($item['institution'] ?? null, 255);

            if (! $degree || ! $institution) {
                continue;
            }

            $valid[] = [
                'degree' => $degree,
                'field_of_study' => $this->truncate($item['field_of_study'] ?? null, 255),
                'institution' => $institution,
                'location' => $this->truncate($item['location'] ?? null, 255),
                'start_date' => $this->truncate($item['start_date'] ?? null, 50),
                'end_date' => $this->truncate($item['end_date'] ?? null, 50),
                'is_current' => isset($item['is_current']) && $item['is_current'] === true,
                'description' => $this->truncate($item['description'] ?? null, 5000),
                'source' => $this->validateSource($item['source'] ?? null),
            ];
        }

        return $valid;
    }

    /**
     * @param  array<int, mixed>  $items
     * @return list<array<string, mixed>>
     */
    private function validateCertifications(array $items): array
    {
        $valid = [];

        foreach ($items as $i => $item) {
            if (! is_array($item)) {
                continue;
            }

            $name = $this->truncate($item['name'] ?? null, 255);
            if (! $name) {
                continue;
            }

            $valid[] = [
                'name' => $name,
                'issuer' => $this->truncate($item['issuer'] ?? null, 255),
                'date' => $this->truncate($item['date'] ?? null, 50),
                'url' => $this->truncate($item['url'] ?? null, 500),
                'description' => $this->truncate($item['description'] ?? null, 5000),
                'source' => $this->validateSource($item['source'] ?? null),
            ];
        }

        return $valid;
    }

    /**
     * @param  array<int, mixed>  $items
     * @return list<array<string, mixed>>
     */
    private function validateLanguages(array $items): array
    {
        $valid = [];

        foreach ($items as $i => $item) {
            if (! is_array($item)) {
                continue;
            }

            $language = $this->truncate($item['language'] ?? null, 100);
            if (! $language) {
                continue;
            }

            $proficiency = $this->truncate($item['proficiency'] ?? null, 50);

            $valid[] = [
                'language' => $language,
                'proficiency' => $proficiency,
                'source' => $this->validateSource($item['source'] ?? null),
            ];
        }

        return $valid;
    }

    /**
     * @param  array<int, mixed>  $items
     * @return list<array<string, mixed>>
     */
    private function validateSkills(array $items): array
    {
        $valid = [];

        foreach ($items as $i => $item) {
            if (! is_array($item)) {
                continue;
            }

            $name = $this->truncate($item['name'] ?? null, 255);
            if (! $name) {
                continue;
            }

            $valid[] = [
                'name' => $name,
                'category' => $this->truncate($item['category'] ?? null, 100),
                'source' => $this->validateSource($item['source'] ?? null),
            ];
        }

        return $valid;
    }

    /**
     * @param  array<int, mixed>  $warnings
     * @return list<string>
     */
    private function validateWarnings(array $warnings): array
    {
        $valid = [];

        foreach ($warnings as $w) {
            if (is_string($w)) {
                $valid[] = mb_substr($w, 0, 1000);
            }
        }

        return $valid;
    }

    /**
     * @param  array<int, mixed>  $items
     * @return list<string>
     */
    private function validateStringArray(array $items): array
    {
        $valid = [];

        foreach ($items as $item) {
            if (is_string($item) && $item !== '') {
                $valid[] = mb_substr($item, 0, 255);
            }
        }

        return $valid;
    }

    /**
     * @param  array<string, mixed>|null  $source
     * @return array<string, mixed>
     */
    private function validateSource(mixed $source): array
    {
        if (! is_array($source)) {
            return ['page' => null, 'text' => null];
        }

        return [
            'page' => isset($source['page']) && is_int($source['page']) ? $source['page'] : null,
            'text' => $this->truncate($source['text'] ?? null, 5000),
        ];
    }

    private function truncate(mixed $value, int $maxLength): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return mb_substr($value, 0, $maxLength);
    }

    private function validateString(mixed $value, int $maxLength): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return mb_substr(trim($value), 0, $maxLength);
    }
}
