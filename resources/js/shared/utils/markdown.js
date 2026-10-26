import { marked } from 'marked'
import DOMPurify from 'dompurify'


marked.setOptions({
    gfm: true,
    breaks: true
})


DOMPurify.addHook('afterSanitizeAttributes', node => {
    if (node.tagName === 'A') {
        node.setAttribute('target', '_blank')
        node.setAttribute('rel', 'noopener noreferrer')
    }
})


export function renderMarkdown(source) {
    const text = String(source || '')

    if (!text.trim()) {
        return ''
    }

    return DOMPurify.sanitize(
        marked.parse(text, { async: false }),
        {
            FORBID_TAGS: ['style', 'form', 'button', 'iframe', 'img'],
            FORBID_ATTR: ['style']
        }
    )
}


// Plain-text version, used for short summaries such as confirm dialogs.
export function markdownSummary(source, limit = 80) {
    const firstLine = String(source || '')
        .split('\n')
        .map(line => line
            .replace(/^\s{0,3}(#{1,6}|>|[-*+]|\d+[.)])\s+/, '')
            .replace(/^\[[ xX]\]\s+/, '')
            .replace(/!?\[([^\]]*)\]\([^)]*\)/g, '$1')
            .replace(/[*_~`]/g, '')
            .trim())
        .find(line => line.length > 0) || ''

    return firstLine.length > limit
        ? `${firstLine.slice(0, limit - 1).trimEnd()}…`
        : firstLine
}
