const IMAGE_EXTENSIONS = new Set(['png', 'jpg', 'jpeg', 'webp', 'gif', 'bmp', 'avif'])
const TEXT_EXTENSIONS = new Set([
    'txt', 'md', 'json', 'xml', 'csv',
    'html', 'css', 'js', 'ts', 'vue', 'php', 'py', 'java', 'c', 'cpp', 'h', 'sql', 'sh', 'yaml', 'yml'
])
const AUDIO_EXTENSIONS = new Set(['mp3', 'wav', 'ogg', 'm4a'])
const VIDEO_EXTENSIONS = new Set(['mp4', 'webm', 'mov'])
const OFFICE_EXTENSIONS = new Set(['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'])
const ARCHIVE_EXTENSIONS = new Set(['zip', 'rar', '7z', 'tar', 'gz'])

export function extensionOf(file) {
    if (file?.extension) {
        return String(file.extension).toLowerCase()
    }

    const name = String(file?.original_filename || file?.display_name || '')
    const parts = name.split('.')

    return parts.length > 1 ? String(parts.pop()).toLowerCase() : ''
}

export function getFileViewerType(file) {
    const mime = String(file?.mime_type || '').toLowerCase()
    const extension = extensionOf(file)

    if (mime === 'image/svg+xml' || extension === 'svg') {
        return 'svg'
    }

    if (mime.startsWith('image/') || IMAGE_EXTENSIONS.has(extension)) {
        return 'image'
    }

    if (mime === 'application/pdf' || extension === 'pdf') {
        return 'pdf'
    }

    if (mime.startsWith('audio/') || AUDIO_EXTENSIONS.has(extension)) {
        return 'audio'
    }

    if (mime.startsWith('video/') || VIDEO_EXTENSIONS.has(extension)) {
        return 'video'
    }

    if (
        mime.startsWith('text/') ||
        mime.includes('json') ||
        mime.includes('xml') ||
        mime.includes('javascript') ||
        mime.includes('typescript') ||
        mime === 'application/x-sh' ||
        TEXT_EXTENSIONS.has(extension)
    ) {
        return 'text'
    }

    if (OFFICE_EXTENSIONS.has(extension)) {
        return 'office'
    }

    if (ARCHIVE_EXTENSIONS.has(extension)) {
        return 'archive'
    }

    return 'generic'
}

export function fileTypeLabel(file) {
    const type = getFileViewerType(file)

    if (type === 'svg') return 'SVG image'
    if (type === 'image') return 'Image'
    if (type === 'pdf') return 'PDF document'
    if (type === 'text') return 'Text file'
    if (type === 'audio') return 'Audio file'
    if (type === 'video') return 'Video file'
    if (type === 'office') return 'Office document'
    if (type === 'archive') return 'Archive'

    return 'Unknown file type'
}
