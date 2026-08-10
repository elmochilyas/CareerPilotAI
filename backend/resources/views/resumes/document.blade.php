<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $resume->title }}</title>
    <style>
        @page { margin: 0; size: A4 portrait; }
        * { box-sizing: border-box; }
        html { background: #e8edf5; }
        body { margin: 0 auto; padding: 35px 38px 32px; width: auto; max-width: 210mm; min-height: 297mm; background: #fff; color: #2b3a50; font-family: "DejaVu Sans", Arial, sans-serif; font-size: 9.6px; line-height: 1.38; }
        h1, h2, p { margin-top: 0; }
        .document-header { margin-bottom: 17px; }
        .candidate-name { margin: 0; color: #142c52; font-size: 28px; line-height: 1.05; font-weight: 700; letter-spacing: .1px; }
        .professional-title { margin: 3px 0 12px; color: #0667ed; font-size: 15.5px; line-height: 1.2; font-weight: 400; }
        .contact-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .contact-table td { width: 33.333%; padding: 1px 18px 1px 0; vertical-align: top; white-space: nowrap; }
        .contact-label { color: #0667ed; margin-right: 5px; }
        .section { margin-top: 14px; }
        .section-heading { margin: 0 0 7px; border-bottom: 1.2px solid #1475ff; padding-bottom: 1px; color: #142c52; font-size: 14.5px; line-height: 1.15; font-weight: 700; text-transform: uppercase; }
        .summary { margin: 0; font-size: 10px; line-height: 1.48; }
        .entry { margin: 0 0 8px; page-break-inside: avoid; break-inside: avoid; }
        .entry:last-child { margin-bottom: 0; }
        .entry-title { margin: 0 0 2px; color: #2b3a50; font-size: 10.3px; font-weight: 700; }
        .entry-date { float: right; margin-left: 12px; color: #52637c; font-size: 9px; font-style: italic; font-weight: 400; }
        .entry-body { margin: 0; }
        .entry-location { margin: 0 0 2px; color: #52637c; font-style: italic; }
        .skills { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .skills td { padding: 4px 6px; vertical-align: top; }
        .skills tr:nth-child(odd) td { background: #e7eef9; }
        .skills-label { width: 21%; color: #0667ed; font-weight: 700; }
        .skills-list { line-height: 1.35; }
        .inline-list { margin: 0; line-height: 1.45; }
        .inline-list span + span::before { content: "  |  "; color: #52637c; }
        .section-languages { page-break-inside: avoid; break-inside: avoid; }
        @media print {
            html { background: #fff; }
            body { width: auto; min-height: 0; padding: 0; }
        }
    </style>
</head>
<body data-resume-template="{{ $templateKey }}">
    <header class="document-header">
        <h1 class="candidate-name">{{ $candidateName }}</h1>
        <p class="professional-title">{{ $preview['headline'] ?: $resume->title }}</p>
        <table class="contact-table" aria-label="Contact details">
            <tr>
                <td><span class="contact-label">Email</span>{{ $profile->user->email }}</td>
                @if ($profile->phone)<td><span class="contact-label">Tel.</span>{{ $profile->phone }}</td>@endif
                @if ($profile->city || $profile->country)<td><span class="contact-label">Location</span>{{ collect([$profile->city, $profile->country])->filter()->implode(', ') }}</td>@endif
            </tr>
            <tr>
                @if ($profile->github_url)<td><span class="contact-label">GitHub</span>{{ preg_replace('#^https?://#', '', $profile->github_url) }}</td>@endif
                @if ($profile->linkedin_url)<td><span class="contact-label">LinkedIn</span>{{ preg_replace('#^https?://#', '', $profile->linkedin_url) }}</td>@endif
            </tr>
        </table>
    </header>

    @if ($preview['summary'])
        <section class="section">
            <h2 class="section-heading">Profile</h2>
            <p class="summary">{{ $preview['summary'] }}</p>
        </section>
    @endif

    @foreach ($preview['sections'] as $section)
        @if (count($section['items']) > 0)
            <section class="section section-{{ $section['type'] }}">
                <h2 class="section-heading">{{ $section['title'] }}</h2>
                @if ($section['type'] === 'skills')
                    @php($skillChunks = array_chunk(array_column($section['items'], 'current_text'), 10))
                    <table class="skills">
                        @foreach ($skillChunks as $index => $skills)
                            <tr>
                                <td class="skills-label">{{ $index === 0 ? 'Core skills' : 'Tools & methods' }}</td>
                                <td class="skills-list">{{ implode(', ', $skills) }}</td>
                            </tr>
                        @endforeach
                    </table>
                @elseif ($section['type'] === 'languages')
                    <p class="inline-list">
                        @foreach ($section['items'] as $item)<span>{{ str_replace(' — ', ' ', $item['current_text']) }}</span>@endforeach
                    </p>
                @else
                    @foreach ($section['items'] as $item)
                        @php($parts = preg_split('/\s+—\s+/u', $item['current_text'], 3) ?: [$item['current_text']])
                        <article class="entry">
                            <p class="entry-title">
                                @if ($item['metadata']['start_date'] ?? null)
                                    <span class="entry-date">{{ \Illuminate\Support\Carbon::parse($item['metadata']['start_date'])->format('M Y') }}@if($item['metadata']['end_date'] ?? null) - {{ \Illuminate\Support\Carbon::parse($item['metadata']['end_date'])->format('M Y') }}@elseif($section['type'] === 'experience') - Present @endif</span>
                                @endif
                                {{ $parts[0] }}@if($section['type'] !== 'projects' && isset($parts[1])) - {{ $parts[1] }}@endif
                            </p>
                            @if ($item['metadata']['location'] ?? null)<p class="entry-location">{{ $item['metadata']['location'] }}</p>@endif
                            @if($section['type'] === 'projects' && isset($parts[1]))
                                <p class="entry-body">{{ $parts[1] }}</p>
                            @elseif(isset($parts[2]))
                                <p class="entry-body">{{ $parts[2] }}</p>
                            @endif
                        </article>
                    @endforeach
                @endif
            </section>
        @endif
    @endforeach
</body>
</html>
