import { useRouter } from 'vue-router';

export function useGlobalActions() {
    const router = useRouter();

    const openContacts = () => {
        router.push({ name: 'contact' });
    };

    const openRecentProjects = () => {
        router.push({ name: 'portfolio' });
    };

    const openWorkflow = () => {
        router.push({ name: 'workflow' });
    };

    const openPrivacyPolicy = () => {
        router.push({ name: 'privacy-policy' });
    };

    const openVcard = () => {
        const link = document.createElement('a');
        link.href = '/vcard.vcf';
        link.download = 'contact.vcf';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    };

    const openEmail = () => {
        window.location.href = 'mailto:hello@studiokristian.com';
    }

    const openPhone = () => {
        window.location.href = 'tel:+421911454678';
    }

    const openWhatsApp = () => {
        const phoneNumber = '+421911454678';
        const message = encodeURIComponent('Hello, I would like to get in touch with you.');
        const url = `https://wa.me/${phoneNumber}?text=${message}`;
        window.open(url, '_blank');
    }

    const openMessage = () => {
        const phoneNumber = '+421911454678';
        const message = encodeURIComponent('Hello, I would like to get in touch with you.');
        const url = `sms:${phoneNumber}?body=${message}`;
        window.open(url, '_blank');
    }

    const openInstagram = () => {
        window.open('https://www.instagram.com/studiokristian/', '_blank');
    }

    const openMessenger = () => {
        window.open('https://m.me/studiokristian', '_blank');
    }

    return {
        openContacts,
        openRecentProjects,
        openWorkflow,
        openPrivacyPolicy,
        openVcard,
        openEmail,
        openPhone,
        openWhatsApp,
        openMessage,
        openInstagram,
        openMessenger,
    };
}