import { Node } from '@tiptap/core'
import {
    VueNodeViewRenderer,
    mergeAttributes
} from '@tiptap/vue-3'

import InfoNodeView from '../nodes/InfoNodeView.vue'


export default Node.create({
    name: 'info',

    group: 'block',

    atom: true,

    selectable: true,

    draggable: true,

    addAttributes() {
        return {
            items: {
                default: []
            },

            heading: {
                default: 'New information'
            },

            text: {
                default: 'Add information here.'
            },

            editable: {
                default: true
            }
        }
    },

    parseHTML() {
        return [
            {
                tag: 'div[data-type="info"]'
            }
        ]
    },

    renderHTML({ HTMLAttributes }) {
        return [
            'div',
            mergeAttributes(
                {
                    'data-type': 'info'
                },
                HTMLAttributes
            )
        ]
    },

    addNodeView() {
        return VueNodeViewRenderer(
            InfoNodeView
        )
    }
})
