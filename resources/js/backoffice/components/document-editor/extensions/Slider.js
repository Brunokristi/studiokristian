import { Node } from '@tiptap/core'
import {
    VueNodeViewRenderer,
    mergeAttributes
} from '@tiptap/vue-3'

import SliderNodeView from '../nodes/SliderNodeView.vue'


export default Node.create({
    name: 'slider',

    group: 'block',

    atom: true,

    selectable: true,

    draggable: true,

    addAttributes() {
        return {
            images: {
                default: []
            },

            language: {
                default: 'en'
            },

            editable: {
                default: true
            }
        }
    },

    parseHTML() {
        return [
            {
                tag: 'div[data-type="slider"]'
            }
        ]
    },

    renderHTML({ HTMLAttributes }) {
        return [
            'div',
            mergeAttributes(
                {
                    'data-type': 'slider'
                },
                HTMLAttributes
            )
        ]
    },

    addNodeView() {
        return VueNodeViewRenderer(
            SliderNodeView
        )
    }
})
