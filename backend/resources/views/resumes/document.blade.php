<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $resume->title }}</title>
    <style>
        @page { margin: 18mm 16mm 16mm 16mm; size: A4 portrait; }
        * { box-sizing: border-box; }
        html { background: #eef2f7; }
        body { margin: 0 auto; padding: 0; width: 100%; max-width: 210mm; min-height: auto; background: #fff; color: #1e293b; font-family: "DejaVu Sans", Arial, Helvetica, sans-serif; font-size: 9.5px; line-height: 1.45; -webkit-font-smoothing: antialiased; }
        h1, h2, h3, p { margin: 0; }
        .page { padding: 28px 32px 24px; }
        .document-header { margin-bottom: 16px; border-bottom: 1.5px solid #0f172a; padding-bottom: 14px; }
        .candidate-name { color: #0f172a; font-size: 26px; line-height: 1.1; font-weight: 700; letter-spacing: -0.2px; }
        .professional-title { margin: 4px 0 10px; color: #334155; font-size: 11.5px; line-height: 1.3; font-weight: 400; }
        .contact-row { display: flex; flex-wrap: wrap; gap: 6px 18px; margin-top: 8px; font-size: 8.5px; color: #475569; }
        .contact-item { white-space: nowrap; }
        .contact-label { color: #64748b; font-weight: 600; margin-right: 4px; text-transform: uppercase; font-size: 7.5px; letter-spacing: 0.4px; }
        .section { margin-top: 16px; page-break-inside: avoid; break-inside: avoid; }
        .section-heading { margin: 0 0 8px; border-bottom: 1px solid #cbd5e1; padding-bottom: 4px; color: #0f172a; font-size: 10.5px; line-height: 1.2; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px; }
        .summary { font-size: 9.5px; line-height: 1.6; color: #334155; max-width: 100%; }
        .entry { margin: 0 0 10px; page-break-inside: avoid; break-inside: avoid; }
        .entry:last-child { margin-bottom: 0; }
        .entry-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; }
        .entry-title { color: #0f172a; font-size: 10px; font-weight: 700; line-height: 1.3; flex: 1; }
        .entry-org { color: #334155; font-weight: 600; }
        .entry-date { color: #64748b; font-size: 8.5px; font-style: italic; white-space: nowrap; text-align: right; }
        .entry-meta { margin: 1px 0 3px; color: #64748b; font-size: 8.5px; font-style: italic; }
        .entry-body { margin: 3px 0 0; color: #334155; font-size: 9px; line-height: 1.5; }
        .entry-body ul { margin: 0; padding-left: 12px; }
        .entry-body li { margin-bottom: 2px; }
        .skills-grid { display: flex; flex-wrap: wrap; gap: 6px; }
        .skill-pill { background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 4px; padding: 3px 7px; font-size: 8.5px; color: #334155; }
        .inline-list { margin: 0; line-height: 1.5; font-size: 9px; }
        .inline-list span + span::before { content: " • "; color: #94a3b8; }
        /* ATS: keep it simple, no background images, no icons, selectable text */
        @media print {
            html { background: #fff; }
            body { padding: 0; }
            .page { padding: 0; }
        }
    </style>
</head>
<body data-resume-template="{{ $templateKey }}">
    <div class="page">
    <header class="document-header">
        <h1 class="candidate-name">{{ $candidateName }}</h1>
        @if($preview['headline'])
            <p class="professional-title">{{ $preview['headline'] }}</p>
        @elseif($profile->headline)
            <p class="professional-title">{{ $profile->headline }}</p>
        @endif
        <div class="contact-row" aria-label="Contact details">
            <span class="contact-item"><span class="contact-label">Email</span>{{ $profile->user->email }}</span>
            @if ($profile->phone)<span class="contact-item"><span class="contact-label">Tel</span>{{ $profile->phone }}</span>@endif
            @if ($profile->city || $profile->country)<span class="contact-item"><span class="contact-label">Location</span>{{ collect([$profile->city, $profile->country])->filter()->implode(', ') }}</span>@endif
            @if ($profile->linkedin_url)<span class="contact-item"><span class="contact-label">LinkedIn</span>{{ preg_replace('#^https?://#', '', rtrim($profile->linkedin_url, '/')) }}</span>@endif
            @if ($profile->github_url)<span class="contact-item"><span class="contact-label">GitHub</span>{{ preg_replace('#^https?://#', '', rtrim($profile->github_url, '/')) }}</span>@endif
            @if ($profile->portfolio_url)<span class="contact-item"><span class="contact-label">Portfolio</span>{{ preg_replace('#^https?://#', '', rtrim($profile->portfolio_url, '/')) }}</span>@endif
        </div>
    </header>

    @if (!empty($preview['summary']))
        <section class="section">
            <h2 class="section-heading">Professional Summary</h2>
            <p class="summary">{{ $preview['summary'] }}</p>
        </section>
    @endif

    @foreach ($preview['sections'] as $section)
        @if (empty($section['items']) || count($section['items']) === 0)
            @continue
        @endif
        <section class="section section-{{ $section['type'] }}">
            <h2 class="section-heading">{{ $section['title'] }}</h2>
            @if ($section['type'] === 'skills')
                <div class="skills-grid">
                    @foreach ($section['items'] as $item)
                        <span class="skill-pill">{{ $item['current_text'] }}</span>
                    @endforeach
                </div>
            @elseif ($section['type'] === 'languages')
                <p class="inline-list">
                    @foreach ($section['items'] as $item)<span>{{ $item['current_text'] }}</span>@endforeach
                </p>
            @else
                @foreach ($section['items'] as $item)
                    @php
                        // Prefer structured metadata, fallback to parsing current_text
                        $meta = $item['metadata'] ?? [];
                        $current = $item['current_text'] ?? '';
                        $parts = preg_split('/\s+—\s+/u', $current, 3) ?: [$current];
                        $title = $parts[0] ?? $current;
                        $org = $parts[1] ?? ($meta['organization'] ?? null);
                        // If current_text was rewritten, metadata may still hold original org/title; use parts as fallback
                        $desc = $parts[2] ?? ($parts[1] ?? null);
                        // For projects, the second part is often description, not org
                        if ($section['type'] === 'projects' && isset($parts[1]) && empty($meta['location'])) {
                            $org = null;
                            $desc = $parts[1];
                        }
                        // Clean up: if desc equals title/org, ignore
                        if ($desc === $title || $desc === $org) $desc = null;
                    @endphp
                    <article class="entry">
                        <div class="entry-header">
                            <div>
                                <p class="entry-title">{{ $title }}@if($org && $section['type'] !== 'projects')<span class="entry-org"> — {{ $org }}</span>@endif</p>
                                @if (!empty($meta['location']) || !empty($item['metadata']['location']))
                                    <p class="entry-meta">{{ $meta['location'] ?? $item['metadata']['location'] ?? '' }}</p>
                                @endif
                            </div>
                            @if (!empty($meta['start_date']) || !empty($meta['end_date']))
                                <span class="entry-date">@if(!empty($meta['start_date'])){{ \Illuminate\Support\Carbon::parse($meta['start_date'])->format('M Y') }}@endif@if(!empty($meta['end_date'])) – {{ \Illuminate\Support\Carbon::parse($meta['end_date'])->format('M Y') }}@elseif($section['type'] === 'experience' && !empty($meta['start_date'])) – Present@endif</span>
                            @elseif (!empty($item['metadata']['start_date']))
                                <span class="entry-date">{{ \Illuminate\Support\Carbon::parse($item['metadata']['start_date'])->format('M Y') }}@if(!empty($item['metadata']['end_date'])) – {{ \Illuminate\Support\Carbon::parse($item['metadata']['end_date'])->format('M Y') }}@endif</span>
                            @endif
                        </div>
                        @if (!empty($desc) && trim($desc) !== '')
                            @php
                                $descLines = preg_split('/\r?\n|•|•\s*/u', $desc, -1, PREG_SPLIT_NO_EMPTY);
                                $isList = count($descLines) > 1 || str_contains($desc, '•') || str_contains($desc, "\n");
                            @endphp
                            @if($isList && count($descLines) > 1)
                                <ul class="entry-body"><li>{{ implode('</li><li>', array_map('e', array_slice($descLines,0,6))) }}</li></ul>
                            @else
                                <p class="entry-body">{{ $desc }}</p>
                            @endif
                        @endif
                        @if($section['type'] === 'projects' && !empty($meta['technologies']))
                            <p class="entry-body" style="margin-top: 4px; font-size: 8px; color:#64748b;"><strong>Tech:</strong> {{ is_array($meta['technologies']) ? implode(', ', array_slice($meta['technologies'],0,8)) : $meta['technologies'] }}</p>
                        @endif
                    </article>
                @endforeach
            @endif
        </section>
    @endforeach
    </div>
</body>
</html>
