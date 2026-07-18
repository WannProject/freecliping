import { Head } from '@inertiajs/react';
import TeamInvitationAlert from '@/components/team-invitation-alert';
import TextLink from '@/components/text-link';
import { home } from '@/routes';
import { request } from '@/routes/password';
import type { TeamInvitationContext } from '@/types';

type Props = {
    status?: string;
    canResetPassword: boolean;
    teamInvitation?: TeamInvitationContext | null;
};

export default function Login({
    status,
    canResetPassword,
    teamInvitation,
}: Props) {
    return (
        <>
            <Head title="Log in" />

            {teamInvitation && (
                <TeamInvitationAlert
                    invitation={teamInvitation}
                    action="Contact an administrator"
                />
            )}

            <div className="space-y-4 text-center text-sm text-muted-foreground">
                <p>
                    Password login is currently unavailable. Contact an
                    administrator if you need access.
                </p>
                <div className="flex justify-center gap-3">
                    <TextLink href={home()}>Return home</TextLink>
                    {canResetPassword && (
                        <TextLink href={request()}>Forgot password?</TextLink>
                    )}
                </div>
            </div>

            {status && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    {status}
                </div>
            )}
        </>
    );
}

Login.layout = {
    title: 'Login unavailable',
    description: 'Password login has been disabled',
};
