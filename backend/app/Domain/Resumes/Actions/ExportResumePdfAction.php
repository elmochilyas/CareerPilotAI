<?php

namespace App\Domain\Resumes\Actions;

use App\Domain\Resumes\Enums\ResumeStatus;
use App\Exceptions\Api\ConflictException;
use App\Models\Resume;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Str;

final readonly class ExportResumePdfAction
{
    public function __construct(private RenderResumeDocumentAction $renderDocument) {}

    /** @return array{content: string, filename: string} */
    public function execute(Resume $resume): array
    {
        if ($resume->status !== ResumeStatus::Approved) {
            throw new ConflictException(
                'Approve the tailored CV before downloading its PDF.',
                'resume_not_approved',
            );
        }

        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->renderDocument->execute($resume), 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();

        return [
            'content' => $dompdf->output(),
            'filename' => Str::slug($resume->title ?: 'tailored-cv').'-v'.$resume->version_no.'.pdf',
        ];
    }
}
