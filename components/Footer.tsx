export default function Footer() {
  return (
    <footer className="bg-gray-900 text-white text-center py-4 mt-12">
      <p>© {new Date().getFullYear()} PlayPoolNation</p>
      <p className="text-sm text-gray-400 mt-1">
        Website created by Couch &amp; Craft —{' '}
        <a
          href="https://www.couchandcraft.com"
          target="_blank"
          rel="noopener noreferrer"
          className="underline hover:text-white"
        >
          www.couchandcraft.com
        </a>
      </p>
    </footer>
  );
}
