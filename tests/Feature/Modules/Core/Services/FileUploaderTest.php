<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use TypiCMS\Modules\Core\Services\FileUploader;

beforeEach(function (): void {
    Storage::fake();
});

it('uploads a jpeg file without throwing a TypeError', function (): void {
    $file = UploadedFile::fake()->image('photo.jpg', 100, 100);

    $result = new FileUploader()->handle($file);

    expect($result)
        ->toBeArray()
        ->toHaveKeys(['filesize', 'mimetype', 'extension', 'filename', 'width', 'height', 'path', 'type'])
        ->and($result['extension'])->toBe('jpg')
        ->and($result['filename'])->toBe('photo.jpg');

    Storage::assertExists($result['path']);
});

it('uploads a jpeg file with alternate extensions', function (string $extension): void {
    $file = UploadedFile::fake()->image("photo.{$extension}", 50, 50);

    $result = new FileUploader()->handle($file);

    expect($result['extension'])->toBe('jpg');
})->with(['jpeg', 'jpe']);

it('uploads a png file', function (): void {
    $file = UploadedFile::fake()->image('image.png', 200, 150);

    $result = new FileUploader()->handle($file);

    expect($result['extension'])->toBe('png')
        ->and($result['filename'])->toBe('image.png');

    Storage::assertExists($result['path']);
});

it('generates a unique filename when file already exists', function (): void {
    $file1 = UploadedFile::fake()->image('photo.png', 10, 10);
    $file2 = UploadedFile::fake()->image('photo.png', 10, 10);

    $result1 = new FileUploader()->handle($file1);
    $result2 = new FileUploader()->handle($file2);

    expect($result1['filename'])->toBe('photo.png')
        ->and($result2['filename'])->toBe('photo_1.png');
});

it('slugifies the filename', function (): void {
    $file = UploadedFile::fake()->image('My Photo (1).png', 10, 10);

    $result = new FileUploader()->handle($file);

    expect($result['filename'])->toBe('my-photo-1.png');
});

function uploadedFileWithContent(string $content, string $clientName): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'upload');
    file_put_contents($path, $content);

    return new UploadedFile($path, $clientName, 'text/plain', null, true);
}

it('does not store a client extension that would be served as executable markup', function (string $clientName): void {
    $file = uploadedFileWithContent('<img src=x onerror="alert(document.cookie)">', $clientName);

    $result = new FileUploader()->handle($file);

    expect($result['extension'])->toBe('txt')
        ->and($result['filename'])->toEndWith('.txt');
})->with(['poc.html', 'poc.htm', 'poc.xhtml', 'poc.xml', 'poc.js']);

it('falls back to an inert extension when the content type is not allowed either', function (): void {
    $file = uploadedFileWithContent("\x00\x01\x02\x03binary\xff\xfe", 'poc.html');

    $result = new FileUploader()->handle($file);

    expect($result['extension'])->toBe('bin');
});

it('keeps the client extension for legitimate files whose content sniffs differently', function (): void {
    $file = uploadedFileWithContent("# Heading\n\nSome markdown.\n", 'notes.md');

    $result = new FileUploader()->handle($file);

    expect($result['extension'])->toBe('md')
        ->and($result['filename'])->toBe('notes.md');
});

it('sanitizes svg content even when the client filename is not .svg', function (): void {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(document.cookie)</script></svg>';
    $file = uploadedFileWithContent($svg, 'evil.html');

    $result = new FileUploader()->handle($file);

    expect(Storage::get($result['path']))->not->toContain('<script>')
        ->and($result['extension'])->toBe('svg');
});

it('sanitizes svg content uploaded as .svg', function (): void {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(document.cookie)</script></svg>';
    $file = uploadedFileWithContent($svg, 'logo.svg');

    $result = new FileUploader()->handle($file);

    expect(Storage::get($result['path']))->not->toContain('<script>')
        ->and($result['extension'])->toBe('svg');
});
