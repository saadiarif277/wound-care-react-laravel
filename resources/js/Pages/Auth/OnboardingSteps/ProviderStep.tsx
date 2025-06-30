import PersonalInfoStep from '@/Components/Onboarding/Steps/PersonalInfoStep';

interface ProviderStepProps {
    data: any;
    setData: (key: string, value: any) => void;
    errors: any;
    states: Array<{ code: string; name: string }>;
}

export default function ProviderStep({ data, setData, errors, states }: ProviderStepProps) {
    const handleChange = (field: string, value: any) => {
        setData(field, value);
    };

    return (
        <PersonalInfoStep
            data={data}
            errors={errors}
            onChange={handleChange}
        />
    );
} 