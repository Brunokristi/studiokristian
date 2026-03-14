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

    const openVcard = () => {
        const link = document.createElement('a');
        link.href = '/vcard.vcf';
        link.download = 'contact.vcf';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    };

    const openEmail = () => {
        window.location.href = 'mailto:brunokristian003@gmail.com';
    }

    const openPhone = () => {
        window.location.href = 'tel:+1234567890';
    }

    const openWhatsApp = () => {
        const phoneNumber = '+1234567890';
        const message = encodeURIComponent('Hello, I would like to get in touch with you.');
        const url = `https://wa.me/${phoneNumber}?text=${message}`;
        window.open(url, '_blank');
    }

    const openMessage = () => {
        const phoneNumber = '+1234567890';
        const message = encodeURIComponent('Hello, I would like to get in touch with you.');
        const url = `sms:${phoneNumber}?body=${message}`;
        window.open(url, '_blank');
    }

    return {
        openContacts,
        openRecentProjects,
        openWorkflow,
        openVcard,
        openEmail,
        openPhone,
        openWhatsApp,
        openMessage,
    };
}