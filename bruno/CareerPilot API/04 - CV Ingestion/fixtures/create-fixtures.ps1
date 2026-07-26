$fixturesDir = Split-Path -Parent $MyInvocation.MyCommand.Path

# --- Create valid-test.pdf ---
$pdfContent = "%PDF-1.4`n" +
"1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj`n" +
"2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj`n" +
"3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]/Contents 4 0 R/Resources<</Font<</F1 5 0 R>>>>>>endobj`n" +
"4 0 obj<</Length 50>>stream`n" +
"BT /F1 12 Tf 100 700 Td (Software Engineer at Acme) Tj ET`n" +
"endstream`n" +
"endobj`n" +
"5 0 obj<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>endobj`n" +
"xref`n" +
"0 6`n" +
"0000000000 65535 f `n" +
"0000000009 00000 n `n" +
"0000000051 00000 n `n" +
"0000000099 00000 n `n" +
"0000000232 00000 n `n" +
"0000000332 00000 n `n" +
"trailer`n" +
"<</Size 6/Root 1 0 R>>`n" +
"startxref`n" +
"393`n" +
"%%EOF"
[System.IO.File]::WriteAllText("$fixturesDir/valid-test.pdf", $pdfContent)
Write-Host "valid-test.pdf: $((Get-Item "$fixturesDir/valid-test.pdf").Length) bytes"

# --- Create valid-test.docx via temporary files and Compress-Archive ---
$tmpDir = Join-Path $fixturesDir "_tmpdocx"
New-Item -ItemType Directory -Path $tmpDir -Force | Out-Null

# Create word subdirectory
New-Item -ItemType Directory -Path "$tmpDir/word" -Force | Out-Null

# Create [Content_Types].xml
@'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
'@ | Set-Content -Path "$tmpDir/[Content_Types].xml" -NoNewline

# Create _rels/.rels
New-Item -ItemType Directory -Path "$tmpDir/_rels" -Force | Out-Null
@'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
'@ | Set-Content -Path "$tmpDir/_rels/.rels" -NoNewline

# Create word/document.xml
@'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Software Engineer at Acme</w:t></w:r></w:p>
    <w:p><w:r><w:t>Full Stack Developer with 5 years experience in Laravel, Vue.js, and MySQL.</w:t></w:r></w:p>
    <w:p><w:r><w:t>Education: BSc Computer Science, University of Casablanca</w:t></w:r></w:p>
    <w:p><w:r><w:t>Skills: Laravel, Vue.js, MySQL, Git, Docker, Python, React Native</w:t></w:r></w:p>
    <w:p><w:r><w:t>Languages: Arabic (Native), French (Advanced), English (Advanced)</w:t></w:r></w:p>
  </w:body>
</w:document>
'@ | Set-Content -Path "$tmpDir/word/document.xml" -NoNewline

# Create word/_rels/document.xml.rels
New-Item -ItemType Directory -Path "$tmpDir/word/_rels" -Force | Out-Null
@'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>
'@ | Set-Content -Path "$tmpDir/word/_rels/document.xml.rels" -NoNewline

# Now compress
Compress-Archive -Path "$tmpDir/*" -DestinationPath "$fixturesDir/temp.zip" -Force
Move-Item -Path "$fixturesDir/temp.zip" -Destination "$fixturesDir/valid-test.docx" -Force

# Cleanup temp
Remove-Item -Path $tmpDir -Recurse -Force
Write-Host "valid-test.docx: $((Get-Item "$fixturesDir/valid-test.docx").Length) bytes"

# --- Create corrupt.pdf ---
[System.IO.File]::WriteAllText("$fixturesDir/corrupt.pdf", "This is not a PDF file at all.")
Write-Host "corrupt.pdf: $((Get-Item "$fixturesDir/corrupt.pdf").Length) bytes"

# --- Create unsupported.txt ---
[System.IO.File]::WriteAllText("$fixturesDir/unsupported.txt", "This is an unsupported text file.")
Write-Host "unsupported.txt: $((Get-Item "$fixturesDir/unsupported.txt").Length) bytes"

# --- Create empty.pdf ---
[System.IO.File]::WriteAllText("$fixturesDir/empty.pdf", "%PDF-1.4 tiny")
Write-Host "empty.pdf: $((Get-Item "$fixturesDir/empty.pdf").Length) bytes"

# --- Create fake-docx.docx (ZIP without word/document.xml) ---
$fakeTmp = Join-Path $fixturesDir "_tmpfake"
New-Item -ItemType Directory -Path $fakeTmp -Force | Out-Null
@'
This is a fake DOCX: it is a ZIP file but does not contain word/document.xml.
'@ | Set-Content -Path "$fakeTmp/readme.txt" -NoNewline

Compress-Archive -Path "$fakeTmp/*" -DestinationPath "$fixturesDir/temp2.zip" -Force
Move-Item -Path "$fixturesDir/temp2.zip" -Destination "$fixturesDir/fake-docx.docx" -Force
Remove-Item -Path $fakeTmp -Recurse -Force
Write-Host "fake-docx.docx: $((Get-Item "$fixturesDir/fake-docx.docx").Length) bytes"

Write-Host "`nAll fixtures created successfully."
Get-ChildItem -Path $fixturesDir -Exclude "*.ps1" | Select-Object Name, Length | Format-Table -AutoSize
