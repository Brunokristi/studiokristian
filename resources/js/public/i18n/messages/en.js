import { viewDepthKey } from "vue-router";

export default {
    home: {
        title: 'Helping businesses figure things out.',

        description:
            'I help businesses build a clear brand, reach the right people and create digital systems that make the business work better.',

        callToAction: 'Let’s figure it out',

        subtitle1:
            'You do not need to know exactly what you need.',

        description1:
            'Maybe you have an idea. Maybe you have a business that has outgrown its website, its brand or the way it currently works. You bring the goal. We figure out what needs to happen next.',

        services: {
            title: 'Here is what I can help with.',

            description:
                'From the way your business looks and communicates to the technology that keeps it running. The exact combination depends on what your business needs.',

            viewAll: 'See all services',
        },

        projects: {
            title: 'The fun part...',

            description:
                'A selection of projects across branding, websites, digital products, marketing and business systems.',
        },

        subtitle2:
            'A good idea is only the beginning.',

        description2:
            'We start with what you are trying to achieve, decide what actually matters and then build the right things. No unnecessary complexity. No technology for the sake of technology.',

        workflow:
            'See how I work',

        recentProjects:
            'See all projects',

        quote:
            'You probably do not need more technology. You need the right technology.',

        quoteText:
            'The goal is not to add more tools to your business. It is to make the right things easier, clearer and more effective.',

        contact:
            'Let’s talk',

        about: {
            title: 'Hey, I’m Bruno',

            intro:
                'fiit stu • developer • designer • occasional genius',

            text:
                'I work across design, technology and business. That means I can help shape a brand, design a digital experience, build the technology behind it or connect the different pieces into a system that actually works.',

            closing:
                'You do not need to have everything figured out before we start. That is part of what I am here for.',
        },

        final: {
            title:
                'So, what are you trying to build?',

            description:
                'Tell me where you are, where you want to go and what is getting in the way. We can figure out the rest together.',

            cta:
                'Let’s talk',
        },
    },

    navigation: {
        home: 'Home',
        portfolio: 'Work',
        services: 'Services',
        workflow: 'Process',
        contact: 'Contact',
    },

    portfolio: {
        contact: 'Have an idea? Let’s talk',
        loading: 'Loading projects...',
        viewLive: 'View live',
        title: 'A few things I’ve worked on.',
        description: 'Brands, websites, marketing, systems and a questionable amount of time spent making things work.',
    },

    footer: {
        portfolio: 'Work',
        contact: 'Contact',
        workflow: 'Process',
        privacy: 'Privacy Policy',
        email: 'Email',
        copyright: 'All rights reserved.',
        facebook: 'Facebook',
        instagram: 'Instagram',
        home: 'Home',
        cookies: 'Cookies',
        company: 'kstdio, s.r.o.',
        location: 'From Bratislava. Not limited by geography.',
        
    },

    contact: {
        call: 'Call',
        email: 'Email',
        whatsapp: 'WhatsApp',
        message: 'Send message',
        instagram: 'Instagram',
        messenger: 'Messenger',
    },

    consultationForm: {
        title: 'First consultation free',
        subtitle: 'Tell us a little about your project and we will get back to you.',
        name: 'First & Last Name',
        service: 'What service are you interested in?',
        servicePlaceholder: 'Select a service',
        servicesLoading: 'Loading services...',
        serviceOther: 'Other project',
        contactMethod: 'How do you prefer to be contacted?',
        contactMethodCall: 'Call',
        contactMethodMessage: 'Message',
        contactMethodEmail: 'Email',
        contactMethodInstagram: 'Instagram',
        contactMethodWhatsapp: 'WhatsApp',
        email: 'Email',
        phone: 'Phone',
        instagram: 'Instagram handle',
        message: 'Message',
        optional: 'optional',
        messagePlaceholder: 'Tell us a little about your project...',
        submit: 'Send request',
        submitting: 'Sending...',
        successTitle: 'Thank you.',
        successText: 'We received your request. We sent you a confirmation email.',
        close: 'Close',
        toastHeading: 'Got it.',
        toastText: 'We received your request. We will get in touch with you shortly.',
        errorHeading: 'Something went wrong',
        errorText: 'Please try again.',
    },

    contactPage: {
        title: 'SK headquarters',
        callEnded: 'Call ended ({time})',
        dragToCall: 'Slide to contact',

        transcript: `Hey, thanks for reaching out. If you are building a business, launching something new, or just know that your business could work a lot better, you are in the right place. I help young businesses figure out what they actually need, from branding and marketing to websites, digital products, systems and automation. You do not need to have everything figured out before we start. Sometimes you just have an idea. Sometimes you have a business that has outgrown the way it currently works. Either way, we can look at it together and figure out what would actually move things forward.`,

        items: [
            {
                heading: 'What exactly do you help with?',
                text: 'I help young businesses turn ideas into something people can find, understand and actually use. That can mean creating a brand, figuring out a marketing direction, building a website, setting up digital systems or finding better ways to use technology in the business.',
            },
            {
                heading: 'I have an idea, but I do not know where to start.',
                text: 'That is actually a good place to start. You do not need a perfect plan or a perfectly written brief. Tell me what you are trying to build and what you want to achieve, and we can figure out what needs to happen first.',
            },
            {
                heading: 'Can you help me launch my business digitally?',
                text: 'Yes. I can help you put the digital side of your business together, from branding and visual identity to your website, marketing, content and the systems behind it. The idea is to build a solid foundation without making things unnecessarily complicated.',
            },
            {
                heading: 'Do you only build websites?',
                text: 'No. A website is often just one part of the bigger picture. I also work on branding, marketing, digital products, automation and custom business systems. What we work on depends on what would actually help your business.',
            },
            {
                heading: 'I already have a business. Can you still help?',
                text: 'Absolutely. You do not need to be starting from zero. Maybe your brand no longer feels right, your website is not bringing in enough customers, your marketing needs direction, or too much of your time is spent doing things manually. We can figure out where there is room to improve.',
            },
            {
                heading: 'Do you use AI?',
                text: 'When it actually helps. I am more interested in solving the right business need than adding AI just because everyone is talking about it. Sometimes AI or automation is the right answer. Sometimes a simple solution is much better.',
            },
            {
                heading: 'How much does it cost?',
                text: 'It depends on what we are trying to achieve. Every business is different, so I prefer to understand what you need first and then define the right scope. You are not going to get a random package just because it is there.',
            },
            {
                heading: 'What if I am not sure what I need?',
                text: 'That is completely fine. You do not have to know the answer before you reach out. Tell me what you are building, what is not working or what you would like to improve. We can figure out the rest together.',
            }
        ],
    },

    services: {
        title: 'The things I can help you with.',
        description:
            'From your first idea to getting the business out into the world and making it work.',
        loading:
            'Loading services...',
        reassure:
            'Your project might need all of this, some of it, or something completely different. We’ll figure it out together.',
        contact:
            'Have an idea? Let’s talk',
        included:
            'Things we can work on',
        notFound:
            'Service not found',
        notFoundDescription:
            'The service you are looking for does not exist or is no longer available.',
    },

    seo: {
        home: {
            title: 'studio kristian | Digital help for young businesses',
            description: 'studio kristian helps young businesses launch, grow and work smarter through branding, marketing, technology and digital systems.',
        },
        portfolio: {
            title: 'Work | studio kristian',
            description: 'Explore selected studio kristian projects across branding, marketing, websites, digital products and business systems.',
        },
        project: {
            title: 'Project | studio kristian',
            description: 'Explore the thinking, design and digital work behind selected studio kristian projects.',
        },
        workflow: {
            title: 'Process | From Idea to Something Real | studio kristian',
            description: 'See how studio kristian helps turn business ideas into brands, digital experiences, products and systems through a practical and collaborative process.',
        },
        contact: {
            title: 'Let’s Talk | studio kristian',
            description: 'Have a business idea, a project or something that could work better? Get in touch with studio kristian and figure out what would actually help.',
        },
        services: {
            title: 'Services | studio kristian',
            description:
                'Explore branding, marketing, websites, digital products and business systems by studio kristian.',
        },

        service: {
            title: 'Service | studio kristian',
            description:
                'Explore this studio kristian service and what it includes.',
        },
    },

    privacy: {
        title: 'Privacy Policy',
        lastUpdated: 'Last updated: March 29, 2026',
        sectionOverviewTitle: 'Overview',
        sectionOverviewText: 'This website is operated by studio kristian. We respect your privacy and only collect limited data that helps us keep the website stable, secure and useful.',
        sectionCookiesTitle: 'Cookies',
        sectionCookiesText: 'This website uses cookies. Some cookies are required for basic functionality. Other cookies help us understand how people use the website so we can improve content, structure and performance.',
        sectionAnalyticsTitle: 'Google Analytics',
        sectionAnalyticsText: 'We use Google Analytics to measure visits, page views and interaction trends. This data is aggregated and does not directly identify you. The purpose is to improve user experience and make the website better over time.',
        sectionControlTitle: 'Your choices',
        sectionControlText: 'You can manage or delete cookies in your browser settings. You can also use browser tools and extensions to block analytics tracking if you prefer.',
        sectionContactTitle: 'Contact',
        sectionContactText: 'If you have questions about this policy or your data, contact us through the contact page on this website.',
    },

    cookies: {
        title: 'Cookies & Analytics',
        text: 'We use cookies and Google Analytics to understand traffic and improve this website. You can accept or reject analytics cookies.',
        learnMore: 'Read privacy policy',
        reject: 'Reject',
        accept: 'Accept all',
        policy: {
            title: "Cookie Settings",
            necessary: "Necessary Cookies",
            necessaryDesc: "Essential for the site to function. These cannot be disabled.",
            necessaryList: "Session cookies, CSRF protection, user preferences",
            analytics: "Analytics Cookies",
            analyticsDesc: "Help us understand how you use the site to improve your experience.",
            analyticsList: "Google Analytics, page views, user behavior tracking",
            marketing: "Marketing Cookies",
            marketingDesc: "Used to track your interests and show you relevant ads.",
            marketingList: "Conversion tracking, audience segmentation, retargeting",
            always: "Always On",
            allowed: "Allowed",
            notAllowed: "Not Allowed",
            canChangeAnytime: "You can change your preferences at any time.",
            rejectAll: "Reject All",
            acceptAll: "Accept All",
            save: "Save Preferences",
            cancel: "Cancel"
        }
    },

    workflowPage: {
        steps: [
            {
                heading: '1. Start with a conversation',
                text: 'Tell me what you are building, what you want to achieve, or what is currently driving you crazy. You do not need a perfect brief.',
            },
            {
                heading: '2. Figure out what actually matters',
                text: 'We look at the business, the audience and the goals. Then we decide what would genuinely help, whether that is branding, marketing, a website, a new product or a better system.',
            },
            {
                heading: '3. Shape the idea',
                text: 'We turn the rough idea into a clear direction. This is where we work through the brand, structure, user experience, content and features before getting too deep into building.',
            },
            {
                heading: '4. Build the right things',
                text: 'The approved direction becomes something real. That could be a brand, website, digital product, campaign, automation or custom system, depending on what the business actually needs.',
            },
            {
                heading: '5. Launch',
                text: 'We get everything ready and put it out into the world. The goal is not just to finish the project, but to make sure it is ready to be used, seen and understood.',
            },
            {
                heading: '6. See what happens',
                text: 'Once something is live, we can see what works, what does not and where people are getting stuck. Real usage is usually more useful than guessing.',
            },
            {
                heading: '7. Make it better',
                text: 'Businesses change. So do their customers. We can keep improving the brand, product, marketing or systems as the business grows.',
            },
        ],

        images: [
            {
                alt: 'Project discovery and planning',
                caption: 'Start with the idea, not a complicated brief',
            },
            {
                alt: 'Brand and digital product design',
                caption: 'Shape the idea into something people can actually use',
            },
            {
                alt: 'Website and digital product development',
                caption: 'Build what the business actually needs',
            },
            {
                alt: 'Live launched project',
                caption: 'Put it out into the world',
            },
            {
                alt: 'Project analytics and improvements',
                caption: 'Learn, improve and keep moving',
            },
        ],

        callToAction: 'Recent Projects',
    },

    
}