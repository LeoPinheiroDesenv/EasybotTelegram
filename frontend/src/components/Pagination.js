import React from 'react';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faChevronLeft, faChevronRight } from '@fortawesome/free-solid-svg-icons';
import './Pagination.css';

const Pagination = ({ 
  currentPage = 1, 
  totalPages = 1, 
  onPageChange,
  showPageNumbers = true,
  maxVisiblePages = 5
}) => {
  if (totalPages <= 1) return null;

  const handlePageClick = (page) => {
    if (page >= 1 && page <= totalPages && page !== currentPage) {
      onPageChange(page);
    }
  };

  const getVisiblePages = () => {
    const pages = [];
    const half = Math.floor(maxVisiblePages / 2);
    let start = Math.max(1, currentPage - half);
    let end = Math.min(totalPages, start + maxVisiblePages - 1);

    if (end - start < maxVisiblePages - 1) {
      start = Math.max(1, end - maxVisiblePages + 1);
    }

    for (let i = start; i <= end; i++) {
      pages.push(i);
    }

    return pages;
  };

  const visiblePages = getVisiblePages();

  return (
    <ul className="pagination d-flex flex-wrap align-items-center gap-2 justify-content-center">
      {/* Previous button */}
      <li className="page-item">
        <button
          onClick={() => handlePageClick(currentPage - 1)}
          disabled={currentPage <= 1}
          className="page-link bg-primary-50 text-secondary-light fw-medium rounded-circle border-0 py-10 d-flex align-items-center justify-content-center h-48-px w-48-px"
          style={{ 
            cursor: currentPage <= 1 ? 'not-allowed' : 'pointer',
            opacity: currentPage <= 1 ? 0.5 : 1
          }}
        >
          <FontAwesomeIcon icon={faChevronLeft} />
        </button>
      </li>

      {/* First page if not visible */}
      {visiblePages[0] > 1 && (
        <>
          <li className="page-item">
            <button
              onClick={() => handlePageClick(1)}
              className="page-link bg-primary-50 text-secondary-light fw-medium rounded-circle border-0 py-10 d-flex align-items-center justify-content-center h-48-px w-48-px"
            >
              1
            </button>
          </li>
          {visiblePages[0] > 2 && (
            <li className="page-item">
              <span className="page-link bg-primary-50 text-secondary-light fw-medium rounded-circle border-0 py-10 d-flex align-items-center justify-content-center h-48-px w-48-px">
                ...
              </span>
            </li>
          )}
        </>
      )}

      {/* Visible page numbers */}
      {showPageNumbers && visiblePages.map((page) => (
        <li key={page} className="page-item">
          <button
            onClick={() => handlePageClick(page)}
            className={`page-link bg-primary-50 text-secondary-light fw-medium rounded-circle border-0 py-10 d-flex align-items-center justify-content-center h-48-px w-48-px ${
              page === currentPage ? 'bg-primary-600 text-white' : ''
            }`}
          >
            {page}
          </button>
        </li>
      ))}

      {/* Last page if not visible */}
      {visiblePages[visiblePages.length - 1] < totalPages && (
        <>
          {visiblePages[visiblePages.length - 1] < totalPages - 1 && (
            <li className="page-item">
              <span className="page-link bg-primary-50 text-secondary-light fw-medium rounded-circle border-0 py-10 d-flex align-items-center justify-content-center h-48-px w-48-px">
                ...
              </span>
            </li>
          )}
          <li className="page-item">
            <button
              onClick={() => handlePageClick(totalPages)}
              className="page-link bg-primary-50 text-secondary-light fw-medium rounded-circle border-0 py-10 d-flex align-items-center justify-content-center h-48-px w-48-px"
            >
              {totalPages}
            </button>
          </li>
        </>
      )}

      {/* Next button */}
      <li className="page-item">
        <button
          onClick={() => handlePageClick(currentPage + 1)}
          disabled={currentPage >= totalPages}
          className="page-link bg-primary-50 text-secondary-light fw-medium rounded-circle border-0 py-10 d-flex align-items-center justify-content-center h-48-px w-48-px"
          style={{ 
            cursor: currentPage >= totalPages ? 'not-allowed' : 'pointer',
            opacity: currentPage >= totalPages ? 0.5 : 1
          }}
        >
          <FontAwesomeIcon icon={faChevronRight} />
        </button>
      </li>
    </ul>
  );
};

export default Pagination;

