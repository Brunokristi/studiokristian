import Image from '@tiptap/extension-image'

import {
    VueNodeViewRenderer,
    mergeAttributes
} from '@tiptap/vue-3'

import ResizableImageNodeView from '../nodes/ResizableImageNodeView.vue'


export default Image.extend({
    addAttributes() {
        return {
            ...this.parent?.(),

            /*
             * Width is ALWAYS the full editor width.
             * It is not a user-resizable attribute anymore.
             */
            width: {
                default: '100%'
            },

            /*
             * Height is the only resizable dimension.
             *
             * null means natural image height.
             */
            height: {
                default: null
            },

            pendingProjectImage: {
                default: false
            }
        }
    },

    renderHTML({
        HTMLAttributes
    }) {
        const width = '100%'

        const height =
            HTMLAttributes?.height

        const styles = [
            `width: ${width}`,
            'max-width: 100%',
            'display: block',
            'margin-left: auto',
            'margin-right: auto',
            'object-fit: contain'
        ]

        if (height) {
            styles.push(
                `height: ${String(height)}`
            )
        } else {
            styles.push(
                'height: auto'
            )
        }

        return [
            'img',
            mergeAttributes(
                this.options.HTMLAttributes,
                HTMLAttributes,
                {
                    style:
                        styles.join('; '),

                    pendingprojectimage:
                        HTMLAttributes?.pendingProjectImage
                            ? 'true'
                            : null
                }
            )
        ]
    },

    addNodeView() {
        return VueNodeViewRenderer(
            ResizableImageNodeView
        )
    }
})