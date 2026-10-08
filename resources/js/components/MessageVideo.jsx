import veraAvatar from '../../images/vera-avatar.png';

export default function MessageVideo({ video }) {
    if (video.status === 'completed' && video.url) {
        return (
            <video
                src={video.url}
                controls
                playsInline
                preload="metadata"
                className="mt-1 mb-2 max-h-48 rounded border border-line-1"
            />
        );
    }

    if (video.status === 'failed') {
        return (
            <p className="text-danger text-[0.7rem] mt-1">
                Video failed: {video.failure_reason || 'unknown error'}
            </p>
        );
    }

    return (
        <span className="mt-1 mb-2 flex items-center gap-2">
            <span className="thinking w-4 h-4 inline-block">
                <img src={veraAvatar} alt="" aria-hidden="true" className="w-full h-full object-contain" />
                <img src={veraAvatar} alt="" aria-hidden="true" className="depth absolute inset-0 w-full h-full object-contain" />
            </span>
            <span className="thinking-label text-sm">
                {video.status === 'generating' ? 'Generating video…' : 'Queued…'}
            </span>
        </span>
    );
}
