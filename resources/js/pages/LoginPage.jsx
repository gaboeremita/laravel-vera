import { useState, useRef, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { route } from 'ziggy-js';
import { api } from '../utils/api.js';
import Portrait from '../components/Portrait.jsx';
import Scanlines from '../components/Scanlines.jsx';
import Header from "../components/Header.jsx";

export default function LoginPage() {
	const [email, setEmail] = useState('');
	const [password, setPassword] = useState('');
	const [isSubmitting, setIsSubmitting] = useState(false);
	const [error, setError] = useState('');
	const emailRef = useRef(null);
	const navigate = useNavigate();

	useEffect(() => {
		api.get(route('user.show')).then((res) => {
			if (res.ok) navigate('/assistants', { replace: true });
		}).catch(() => {});
	}, []);

	useEffect(() => {
		if (emailRef.current) emailRef.current.focus();
	}, []);

	const handleLogin = async (e) => {
		e.preventDefault();
		if (!email.trim() || !password || isSubmitting) return;

		setIsSubmitting(true);
		setError('');

		try {
			await api.getCsrfCookie();
			const res = await api.login(email.trim(), password);

			if (res.ok) {
				navigate('/assistants', { replace: true });
			} else {
				setError('Authentication failed. Check your email and password.');
				setIsSubmitting(false);
			}
		} catch {
			setError('Connection error. Try again.');
			setIsSubmitting(false);
		}
	};

	return (
		<div className="w-full h-screen bg-bg-0 flex relative overflow-hidden">
			<Scanlines />
			<div className="absolute inset-0 pointer-events-none z-[11] viewport-ambient" />

			<div className="w-[35%] min-w-50 max-w-400 shrink-0 border-r border-line-1 relative z-5">
				<Portrait
					emotion="default"
					authenticated={false}
					getImageUrl={() => null}
					getVideoUrl={() => null}
				/>
			</div>

			<div className="flex-1 flex flex-col relative z-5 min-w-0">
				<Header
					hideSettings
					align="center"
					status={{ label: 'OFFLINE', color: 'text-fg-3', dot: '○', blink: false }}
				>
					<span className="text-accent text-3xl font-bold tracking-[0.5em] ml-4">VERA</span>
				</Header>

				<div className="flex-1 flex items-center justify-center p-6">
					<form onSubmit={handleLogin} className="w-full max-w-sm bg-bg-1 border border-line-1 rounded-lg p-8">
						<h1 className="text-fg-1 text-lg font-semibold tracking-[0.02em]">
							Welcome to VERA
						</h1>
						<p className="text-fg-3 text-sm mt-1 mb-6">
							Please log in to continue
						</p>

						<div className="space-y-4">
							<div>
								<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block mb-1">
									Email
								</label>
								<input
									ref={emailRef}
									type="email"
									value={email}
									onChange={(e) => setEmail(e.target.value)}
									disabled={isSubmitting}
									className="w-full bg-bg-0 border border-line-1 text-fg-1 text-sm px-3 py-2 outline-none focus:border-accent/50 transition-colors"
									placeholder="you@example.com"
								/>
							</div>

							<div>
								<label className="text-fg-3 text-[0.65rem] tracking-[0.1em] uppercase block mb-1">
									Password
								</label>
								<input
									type="password"
									value={password}
									onChange={(e) => setPassword(e.target.value)}
									disabled={isSubmitting}
									className="w-full bg-bg-0 border border-line-1 text-fg-1 text-sm px-3 py-2 outline-none focus:border-accent/50 transition-colors"
									placeholder="••••••••"
								/>
							</div>
						</div>

						{error && (
							<p className="text-danger text-xs mt-4">{error}</p>
						)}

						<button
							type="submit"
							disabled={isSubmitting || !email.trim() || !password}
							className="button-primary w-full mt-6"
						>
							{isSubmitting ? 'LOGGING IN…' : 'LOG IN'}
						</button>
					</form>
				</div>
			</div>
		</div>
	);
}
