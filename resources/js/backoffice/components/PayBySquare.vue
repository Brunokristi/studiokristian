<script setup>

import {
    computed
} from 'vue'


const props = defineProps({

    payment: {
        type: Object,
        required: true
    },

    locale: {
        type: String,
        default: 'sk'
    },

    showDetails: {
        type: Boolean,
        default: true
    }

})


const copy =
    computed(() => {

        if (
            props.locale ===
            'sk'
        ) {

            return {
                title:
                    'Zaplaťte cez PAY by square',

                description:
                    'Naskenujte QR kód vo svojej bankovej aplikácii.',

                amount:
                    'Suma',

                iban:
                    'IBAN',

                variableSymbol:
                    'Variabilný symbol',

                dueDate:
                    'Splatnosť'
            }

        }


        return {
            title:
                'Pay with PAY by square',

            description:
                'Scan the QR code with your banking app.',

            amount:
                'Amount',

            iban:
                'IBAN',

            variableSymbol:
                'Variable symbol',

            dueDate:
                'Due date'
        }

    })


const formattedAmount =
    computed(() => {

        const amount =
            Number(
                props.payment
                    ?.amount ||
                0
            )


        try {

            return new Intl
                .NumberFormat(
                    props.locale ===
                        'sk'
                        ? 'sk-SK'
                        : 'en-GB',
                    {
                        style:
                            'currency',

                        currency:
                            props.payment
                                ?.currency ||
                            'EUR'
                    }
                )
                .format(
                    amount
                )

        } catch {

            return (
                `${amount.toFixed(2)} `
                + (
                    props.payment
                        ?.currency ||
                    'EUR'
                )
            )

        }

    })

</script>


<template>

    <section
        v-if="
            payment?.qr_code
        "
        class="
            border
            border-accent
            bg-light
        "
    >

        <div
            class="
                grid
                gap-8
                p-6
                md:grid-cols-[minmax(0,1fr)_180px]
                md:items-center
            "
        >

            <div>

                <h3
                    class="
                        h3
                        uppercase
                        text-accent
                    "
                >

                    {{
                        copy.title
                    }}

                </h3>


                <p
                    class="
                        p
                        mt-2
                        text-dark/60
                    "
                >

                    {{
                        copy.description
                    }}

                </p>


                <dl
                    v-if="
                        showDetails
                    "
                    class="
                        mt-6
                        grid
                        gap-3
                    "
                >

                    <div
                        class="
                            grid
                            grid-cols-[140px_minmax(0,1fr)]
                            gap-4
                        "
                    >

                        <dt
                            class="
                                font-mono
                                text-xs
                                font-bold
                                uppercase
                                text-dark/50
                            "
                        >
                            {{
                                copy.amount
                            }}
                        </dt>


                        <dd
                            class="
                                p
                                text-dark
                            "
                        >
                            {{
                                formattedAmount
                            }}
                        </dd>

                    </div>


                    <div
                        v-if="
                            payment.iban
                        "
                        class="
                            grid
                            grid-cols-[140px_minmax(0,1fr)]
                            gap-4
                        "
                    >

                        <dt
                            class="
                                font-mono
                                text-xs
                                font-bold
                                uppercase
                                text-dark/50
                            "
                        >
                            {{
                                copy.iban
                            }}
                        </dt>


                        <dd
                            class="
                                p
                                break-all
                                text-dark
                            "
                        >
                            {{
                                payment.iban
                            }}
                        </dd>

                    </div>


                    <div
                        v-if="
                            payment.variable_symbol
                        "
                        class="
                            grid
                            grid-cols-[140px_minmax(0,1fr)]
                            gap-4
                        "
                    >

                        <dt
                            class="
                                font-mono
                                text-xs
                                font-bold
                                uppercase
                                text-dark/50
                            "
                        >
                            {{
                                copy.variableSymbol
                            }}
                        </dt>


                        <dd
                            class="
                                p
                                text-dark
                            "
                        >
                            {{
                                payment
                                    .variable_symbol
                            }}
                        </dd>

                    </div>


                    <div
                        v-if="
                            payment.due_date
                        "
                        class="
                            grid
                            grid-cols-[140px_minmax(0,1fr)]
                            gap-4
                        "
                    >

                        <dt
                            class="
                                font-mono
                                text-xs
                                font-bold
                                uppercase
                                text-dark/50
                            "
                        >
                            {{
                                copy.dueDate
                            }}
                        </dt>


                        <dd
                            class="
                                p
                                text-dark
                            "
                        >
                            {{
                                payment.due_date
                            }}
                        </dd>

                    </div>

                </dl>

            </div>


            <div
                class="
                    flex
                    flex-col
                    items-center
                    justify-center
                "
            >

                <img
                    :src="
                        payment.qr_code
                    "
                    alt="PAY by square"
                    class="
                        block
                        h-auto
                        w-full
                        max-w-[180px]
                        bg-white
                    "
                >


                <div
                    class="
                        mt-3
                        font-mono
                        text-xs
                        font-bold
                    "
                >

                    <span
                        class="
                            text-accent
                        "
                    >
                        PAY
                    </span>

                    <span
                        class="
                            text-dark/50
                        "
                    >
                        by square
                    </span>

                </div>

            </div>

        </div>

    </section>

</template>