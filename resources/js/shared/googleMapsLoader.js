import {
    setOptions
} from '@googlemaps/js-api-loader'


let configured = false


export function configureGoogleMaps(
    key
) {
    if (
        configured ||
        !key
    ) {
        return
    }

    setOptions({
        key,
        v: 'weekly',
        language: 'sk',
        region: 'SK'
    })

    configured = true
}
